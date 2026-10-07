import { useHttp } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';

import {
    store,
    students as studentSearch,
} from '@/actions/Modules/Attendance/App/Http/Controllers/ScanController';
import { EmptyState, OptionSelect, Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import { cn } from '@shared/lib/utils';

import AttendancePage from '../../Components/AttendancePage';
import ClassSelect from '../../Components/ClassSelect';
import Filter from '../../Components/Filter';
import type { ClassOption } from '../../Components/status';

type Mode = 'gate-in' | 'gate-out' | 'lesson';

interface Student {
    id: number;
    name: string;
    nis: string;
    class: string | null;
}

interface Recorded {
    student: Student;
    time: string | null;
    status: string;
}

interface Entry {
    key: number;
    ok: boolean;
    title: string;
    detail: string;
}

interface ScanProps {
    date: { iso: string; label: string };
    can: { gate: boolean; lesson: boolean };
    ownLessonOnly: boolean;
    classes: ClassOption[];
    classId: string;
    slots: { value: string; label: string }[];
    slotId: string;
}

type Payload = {
    mode: Mode;
    token?: string;
    student_id?: number;
    class_id?: string;
    period_slot_id?: string;
};

const modeLabels: Record<Mode, string> = {
    'gate-in': 'Gerbang · Masuk',
    'gate-out': 'Gerbang · Pulang',
    lesson: 'Kelas · Jam pelajaran',
};

/** A camera keeps reading the code it sees: the same code is sent once. */
const REPEAT_GUARD_MS = 4000;

/**
 * Pindai QR: record students at the gate or in a lesson from their
 * one-time QR — by camera, by a handheld scanner typing into the code
 * field — or by picking them by name or NIS.
 */
export default function Scan({
    date,
    can,
    ownLessonOnly,
    classes,
    classId,
    slots,
    slotId,
}: ScanProps) {
    const modes: Mode[] = [
        ...(can.gate ? (['gate-in', 'gate-out'] as Mode[]) : []),
        ...(can.lesson ? (['lesson'] as Mode[]) : []),
    ];

    const [mode, setMode] = useState<Mode>(modes[0]);
    const [lessonClass, setLessonClass] = useState(classId);
    const [lessonSlot, setLessonSlot] = useState(slotId);
    const [log, setLog] = useState<Entry[]>([]);
    const [code, setCode] = useState('');
    const [term, setTerm] = useState('');
    const [found, setFound] = useState<Student[] | null>(null);
    const [camera, setCamera] = useState(false);
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [latest, setLatest] = useState<Entry | null>(null);

    const scan = useHttp<Payload, Recorded>({ mode: modes[0] });
    const search = useHttp<Record<string, never>, { students: Student[] }>({});

    const video = useRef<HTMLVideoElement>(null);
    const busy = useRef(false);
    const last = useRef<{ token: string; at: number } | null>(null);
    const entryKey = useRef(0);

    const lessonReady =
        mode !== 'lesson' || (lessonClass !== '' && lessonSlot !== '');

    const add = (ok: boolean, title: string, detail: string) => {
        const entry = { key: ++entryKey.current, ok, title, detail };

        setLog((entries) => [entry, ...entries].slice(0, 30));
        setLatest(entry);
    };

    const record = (target: { token: string } | { student_id: number }) => {
        if (busy.current || !lessonReady) {
            return;
        }

        busy.current = true;
        scan.transform(() => ({
            mode,
            ...target,
            ...(mode === 'lesson'
                ? { class_id: lessonClass, period_slot_id: lessonSlot }
                : {}),
        }));
        scan.post(store.url(), {
            headers: { Accept: 'application/json' },
            onSuccess: (response) =>
                add(
                    true,
                    response.student.name,
                    [response.student.class, response.status, response.time]
                        .filter(Boolean)
                        .join(' · '),
                ),
            onError: (errors) =>
                add(
                    false,
                    'Tidak tercatat',
                    String(Object.values(errors)[0] ?? 'Permintaan ditolak.'),
                ),
            onHttpException: () =>
                add(
                    false,
                    'Tidak tercatat',
                    'Permintaan ditolak. Muat ulang halaman lalu coba lagi.',
                ),
            onNetworkError: () =>
                add(
                    false,
                    'Tidak tercatat',
                    'Tidak ada sambungan. Periksa internet lalu coba lagi.',
                ),
            onFinish: () => {
                busy.current = false;
            },
        }).catch(() => undefined);
    };

    useEffect(() => {
        if (latest === null) {
            return;
        }

        const timer = setTimeout(() => setLatest(null), 4000);

        return () => clearTimeout(timer);
    }, [latest]);

    // The camera callback outlives a render: it always calls the latest `record`.
    const recordRef = useRef(record);

    useEffect(() => {
        recordRef.current = record;
    });

    useEffect(() => {
        if (!camera || video.current === null) {
            return;
        }

        let scanner: {
            start: () => Promise<void>;
            destroy: () => void;
        } | null = null;
        let stopped = false;

        void import('qr-scanner').then(({ default: QrScanner }) => {
            if (stopped || video.current === null) {
                return;
            }

            scanner = new QrScanner(
                video.current,
                (result) => {
                    const now = Date.now();

                    if (
                        last.current !== null &&
                        last.current.token === result.data &&
                        now - last.current.at < REPEAT_GUARD_MS
                    ) {
                        return;
                    }

                    last.current = { token: result.data, at: now };
                    recordRef.current({ token: result.data });
                },
                {
                    preferredCamera: 'environment',
                    returnDetailedScanResult: true,
                    highlightScanRegion: true,
                    maxScansPerSecond: 5,
                },
            );

            scanner
                .start()
                .then(() => {
                    // The wrapper mirrors the view; drop the library's own flip.
                    if (video.current !== null) {
                        video.current.style.transform = 'none';
                    }
                })
                .catch(() => {
                    setCameraError(
                        'Kamera tidak bisa dibuka. Izinkan akses kamera, atau pakai kolom kode dan pencarian di bawah.',
                    );
                    setCamera(false);
                });
        });

        return () => {
            stopped = true;
            scanner?.destroy();
        };
    }, [camera]);

    const submitCode = (event: FormEvent) => {
        event.preventDefault();

        if (code.trim() !== '') {
            record({ token: code.trim() });
            setCode('');
        }
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();

        if (term.trim() === '') {
            setFound(null);

            return;
        }

        search
            .get(studentSearch.url({ query: { q: term.trim() } }), {
                headers: { Accept: 'application/json' },
                onSuccess: (response) => setFound(response.students),
            })
            .catch(() => setFound([]));
    };

    if (modes.length === 0) {
        return (
            <AttendancePage title="Pindai QR" description={date.label}>
                <EmptyState>Anda tidak punya izin mencatat absensi.</EmptyState>
            </AttendancePage>
        );
    }

    return (
        <AttendancePage
            title="Pindai QR"
            description={date.label}
            width="max-w-3xl"
        >
            <div
                role="group"
                aria-label="Mode"
                className="mb-6 flex flex-wrap gap-2"
            >
                {modes.map((option) => (
                    <Button
                        key={option}
                        type="button"
                        variant={mode === option ? 'default' : 'outline'}
                        aria-pressed={mode === option}
                        onClick={() => setMode(option)}
                    >
                        {modeLabels[option]}
                    </Button>
                ))}
            </div>

            {mode === 'lesson' && (
                <div className="mb-6 flex flex-col gap-4 sm:flex-row">
                    <Filter label="Kelas">
                        <ClassSelect
                            value={lessonClass}
                            onChange={setLessonClass}
                            options={classes}
                        />
                    </Filter>
                    {slots.length > 0 && (
                        <Filter label="Jam pelajaran">
                            <OptionSelect
                                label="Jam pelajaran"
                                value={lessonSlot}
                                onChange={setLessonSlot}
                                options={slots}
                            />
                        </Filter>
                    )}
                </div>
            )}

            {!lessonReady ? (
                <EmptyState>
                    {ownLessonOnly
                        ? 'Tidak ada pelajaran Anda yang sedang berlangsung. Pemindaian hanya bisa dilakukan pada jam mengajar Anda.'
                        : slots.length === 0
                          ? 'Tidak ada jam pelajaran hari ini. Atur jam pelajaran di Akademik › Jam Pelajaran.'
                          : 'Pilih kelas dan jam pelajaran lebih dulu.'}
                </EmptyState>
            ) : (
                <div className="flex flex-col gap-6">
                    <Panel
                        title="Kamera"
                        actions={
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setCameraError(null);
                                    setCamera((on) => !on);
                                }}
                            >
                                {camera ? 'Matikan kamera' : 'Nyalakan kamera'}
                            </Button>
                        }
                    >
                        {cameraError !== null && (
                            <Alert variant="destructive" className="mb-4">
                                <AlertDescription>
                                    {cameraError}
                                </AlertDescription>
                            </Alert>
                        )}
                        <div
                            className={cn(
                                'relative scale-x-100 overflow-hidden',
                                !camera && 'hidden',
                            )}
                        >
                            <video
                                ref={video}
                                muted
                                playsInline
                                className="aspect-video w-full bg-muted object-cover"
                            />
                        </div>
                        {latest !== null && (
                            <div
                                role="status"
                                aria-live="assertive"
                                className={cn(
                                    'mt-4 flex items-center justify-between gap-3 rounded-lg border-2 p-4',
                                    latest.ok
                                        ? 'border-primary bg-primary/10'
                                        : 'border-destructive bg-destructive/10',
                                )}
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-lg font-semibold">
                                        {latest.title}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {latest.detail}
                                    </p>
                                </div>
                                <Badge
                                    variant={
                                        latest.ok ? 'default' : 'destructive'
                                    }
                                >
                                    {latest.ok ? 'Tercatat' : 'Ditolak'}
                                </Badge>
                            </div>
                        )}
                        {!camera && (
                            <p className="text-sm text-muted-foreground">
                                Nyalakan kamera lalu arahkan ke QR di HP siswa.
                                Kamera butuh sambungan aman (https).
                            </p>
                        )}
                    </Panel>

                    <Panel title="Kode QR">
                        <form
                            onSubmit={submitCode}
                            className="flex flex-col gap-3 sm:flex-row sm:items-end"
                        >
                            <Field className="flex-1">
                                <FieldLabel htmlFor="scan-code">
                                    Kode
                                </FieldLabel>
                                <Input
                                    id="scan-code"
                                    name="code"
                                    autoComplete="off"
                                    value={code}
                                    onChange={(event) =>
                                        setCode(event.target.value)
                                    }
                                />
                                <FieldDescription>
                                    Untuk alat pemindai yang mengetik sendiri:
                                    klik kolom ini, lalu pindai.
                                </FieldDescription>
                            </Field>
                            <Button
                                type="submit"
                                disabled={scan.processing || code.trim() === ''}
                            >
                                Catat
                            </Button>
                        </form>
                    </Panel>

                    <Panel title="Tanpa HP: cari siswa">
                        <form
                            onSubmit={submitSearch}
                            className="flex flex-col gap-3 sm:flex-row sm:items-end"
                        >
                            <Field className="flex-1">
                                <FieldLabel htmlFor="scan-search">
                                    Nama atau NIS
                                </FieldLabel>
                                <Input
                                    id="scan-search"
                                    name="q"
                                    autoComplete="off"
                                    value={term}
                                    onChange={(event) =>
                                        setTerm(event.target.value)
                                    }
                                />
                            </Field>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={search.processing}
                            >
                                Cari
                            </Button>
                        </form>

                        {found !== null &&
                            (found.length === 0 ? (
                                <p className="mt-4 text-sm text-muted-foreground">
                                    Tidak ada siswa aktif yang cocok.
                                </p>
                            ) : (
                                <ul className="mt-4 flex flex-col divide-y divide-border">
                                    {found.map((student) => (
                                        <li
                                            key={student.id}
                                            className="flex items-center justify-between gap-3 py-2"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {student.name}
                                                </p>
                                                <p className="font-mono text-xs text-muted-foreground">
                                                    {student.nis} ·{' '}
                                                    {student.class ??
                                                        'tanpa kelas'}
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                size="sm"
                                                disabled={scan.processing}
                                                onClick={() =>
                                                    record({
                                                        student_id: student.id,
                                                    })
                                                }
                                                aria-label={`Catat ${student.name}`}
                                            >
                                                Catat{' '}
                                                {modeLabels[mode].toLowerCase()}
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            ))}
                    </Panel>

                    <Panel title="Hasil">
                        {log.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada yang dicatat.
                            </p>
                        ) : (
                            <ul
                                className="flex flex-col divide-y divide-border"
                                aria-live="polite"
                            >
                                {log.map((entry) => (
                                    <li
                                        key={entry.key}
                                        className="flex items-center justify-between gap-3 py-2"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {entry.title}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {entry.detail}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={
                                                entry.ok
                                                    ? 'default'
                                                    : 'destructive'
                                            }
                                        >
                                            {entry.ok ? 'Tercatat' : 'Ditolak'}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>
            )}
        </AttendancePage>
    );
}
