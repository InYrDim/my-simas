import { router, useHttp } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { token as tokenRoute } from '@/actions/Modules/Attendance/App/Http/Controllers/StudentQrController';
import { DefinitionList, Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Button } from '@shared/components/ui/button';
import { Skeleton } from '@shared/components/ui/skeleton';

import AttendancePage from '../../Components/AttendancePage';

interface MyQrProps {
    student: { name: string; nis: string; class: string | null };
    today: {
        label: string;
        status: string | null;
        checkedIn: string | null;
        checkedOut: string | null;
    };
    /** Seconds between two codes; shorter than a code's life. */
    refreshEvery: number;
}

/**
 * QR Absensi: the student's own one-time code. It replaces itself before
 * it expires, so a screenshot is useless a minute later.
 */
export default function MyQr({ student, today, refreshEvery }: MyQrProps) {
    const http = useHttp<
        Record<string, never>,
        { token: string; expiresIn: number }
    >({});
    const [code, setCode] = useState<string | null>(null);
    const [left, setLeft] = useState(refreshEvery);
    const [failed, setFailed] = useState<string | null>(null);

    // `http.post` is a new function on every render; the timer below must
    // not restart with it, so it reads the latest one through a ref.
    const post = useRef(http.post);

    useEffect(() => {
        post.current = http.post;
    });

    const refresh = useCallback(() => {
        post.current(tokenRoute.url(), {
            headers: { Accept: 'application/json' },
            onSuccess: (response) => {
                setCode(response.token);
                setLeft(refreshEvery);
                setFailed(null);
                // What the gate recorded meanwhile.
                router.reload({ only: ['today'] });
            },
            onHttpException: () => {
                setCode(null);
                setFailed(
                    'Kode belum bisa dibuat. Tunggu sebentar lalu coba lagi.',
                );
            },
            onNetworkError: () => {
                setCode(null);
                setFailed(
                    'Tidak ada sambungan. Periksa internet lalu coba lagi.',
                );
            },
        }).catch(() => undefined);
    }, [refreshEvery]);

    useEffect(() => {
        refresh();
    }, [refresh]);

    useEffect(() => {
        if (code === null) {
            return;
        }

        const timer = window.setInterval(() => {
            setLeft((seconds) => {
                if (seconds <= 1) {
                    refresh();

                    return refreshEvery;
                }

                return seconds - 1;
            });
        }, 1000);

        return () => window.clearInterval(timer);
    }, [code, refresh, refreshEvery]);

    return (
        <AttendancePage
            title="QR Absensi"
            description={`${student.name} · ${student.class ?? 'belum ada kelas'}`}
            width="max-w-md"
        >
            <Panel>
                <div className="flex flex-col items-center gap-4">
                    {failed !== null ? (
                        <>
                            <Alert variant="destructive">
                                <AlertDescription>{failed}</AlertDescription>
                            </Alert>
                            <Button
                                onClick={refresh}
                                disabled={http.processing}
                            >
                                Coba lagi
                            </Button>
                        </>
                    ) : code === null ? (
                        <Skeleton className="size-64" />
                    ) : (
                        <>
                            {/* Always dark on white: a scanner needs the contrast in any theme. */}
                            <div
                                className="bg-white p-4"
                                data-testid="attendance-qr"
                                data-code={code}
                            >
                                <QRCodeSVG
                                    value={code}
                                    size={224}
                                    level="M"
                                    bgColor="#ffffff"
                                    fgColor="#000000"
                                    title="QR absensi"
                                />
                            </div>
                            <p
                                className="text-sm text-muted-foreground"
                                role="status"
                            >
                                Kode berganti dalam {left} detik
                            </p>
                        </>
                    )}
                    <p className="text-center text-sm text-muted-foreground">
                        Tunjukkan kode ini kepada petugas di gerbang atau guru
                        di kelas. Kode hanya berlaku sekali.
                    </p>
                </div>
            </Panel>

            <Panel title="Hari ini" className="mt-6">
                <DefinitionList
                    rows={[
                        ['Tanggal', today.label],
                        ['Status', today.status ?? 'Belum tercatat'],
                        ['Masuk', today.checkedIn ?? '—'],
                        ['Pulang', today.checkedOut ?? '—'],
                    ]}
                />
            </Panel>
        </AttendancePage>
    );
}
