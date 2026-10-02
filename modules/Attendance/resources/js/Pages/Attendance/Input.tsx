import { router, useForm } from '@inertiajs/react';

import { index, update } from '@/actions/Modules/Attendance/App/Http/Controllers/DailyInputController';
import { EmptyState, Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Input as TextInput } from '@shared/components/ui/input';
import { cn } from '@shared/lib/utils';

import AttendancePage from '../../Components/AttendancePage';
import ClassSelect from '../../Components/ClassSelect';
import Filter from '../../Components/Filter';
import { dailyStatuses, pressedClass, statusMeta } from '../../Components/status';
import type { AttendanceStatus, ClassOption } from '../../Components/status';

interface Student {
    id: number;
    name: string;
    nis: string;
    status: AttendanceStatus | null;
    note: string | null;
    checkedIn: string | null;
    checkedOut: string | null;
}

interface InputProps {
    date: { iso: string; label: string; isToday: boolean };
    today: string;
    classes: ClassOption[];
    classId: string;
    students: Student[];
}

interface Mark {
    student_id: number;
    status: AttendanceStatus;
    note: string;
}

/**
 * Input Absensi: one class, one day. A student without a record starts as
 * present, so the teacher only touches the exceptions.
 */
export default function Input({ date, today, classes, classId, students }: InputProps) {
    const open = (kelas: string, tanggal: string) =>
        router.get(index.url({ query: { kelas, tanggal } }));

    return (
        <AttendancePage title="Input Absensi" description={date.label} width="max-w-3xl">
            <div className="mb-6 flex flex-col gap-4 sm:flex-row">
                <Filter label="Kelas">
                    <ClassSelect
                        value={classId}
                        onChange={(value) => open(value, date.iso)}
                        options={classes}
                    />
                </Filter>
                <Filter label="Tanggal" htmlFor="input-date">
                    <TextInput
                        id="input-date"
                        type="date"
                        value={date.iso}
                        max={today}
                        onChange={(event) =>
                            event.target.value !== '' && open(classId, event.target.value)
                        }
                    />
                </Filter>
            </div>

            {classId === '' ? (
                <EmptyState>
                    Belum ada kelas pada tahun ajaran aktif. Aktifkan tahun ajaran dan
                    buat kelas di Master Data lebih dulu.
                </EmptyState>
            ) : students.length === 0 ? (
                <EmptyState>Belum ada siswa aktif di kelas ini.</EmptyState>
            ) : (
                // A new class or day is a new form.
                <RollCall
                    key={`${classId}-${date.iso}`}
                    classId={classId}
                    date={date.iso}
                    students={students}
                />
            )}
        </AttendancePage>
    );
}

function RollCall({
    classId,
    date,
    students,
}: {
    classId: string;
    date: string;
    students: Student[];
}) {
    const form = useForm<{ class_id: string; date: string; marks: Mark[] }>({
        class_id: classId,
        date,
        marks: students.map((student) => ({
            student_id: student.id,
            status: student.status ?? 'present',
            note: student.note ?? '',
        })),
    });
    const marks = form.data.marks;
    const unrecorded = students.filter((student) => student.status === null).length;

    const change = (index: number, patch: Partial<Mark>) =>
        form.setData(
            'marks',
            marks.map((mark, position) => (position === index ? { ...mark, ...patch } : mark)),
        );

    const error = form.errors.marks ?? form.errors.date ?? form.errors.class_id;

    return (
        <>
            {error !== undefined && (
                <Alert variant="destructive" className="mb-6">
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            {unrecorded > 0 && (
                <p className="mb-3 text-sm text-muted-foreground">
                    {unrecorded} siswa belum diabsen dan ditampilkan sebagai Hadir. Ubah
                    yang tidak hadir, lalu simpan.
                </p>
            )}

            <Panel>
                <ul className="flex flex-col divide-y divide-border">
                    {students.map((student, index) => {
                        const mark = marks[index];
                        const away = mark.status !== 'present' && mark.status !== 'late';

                        return (
                            <li key={student.id} className="flex flex-col gap-3 py-3 first:pt-0 last:pb-0">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">{student.name}</p>
                                        <p className="font-mono text-xs text-muted-foreground">
                                            {student.nis}
                                            {student.checkedIn !== null && ` · masuk ${student.checkedIn}`}
                                            {student.checkedOut !== null && ` · pulang ${student.checkedOut}`}
                                        </p>
                                    </div>
                                    <div
                                        role="group"
                                        aria-label={`Status ${student.name}`}
                                        className="flex flex-wrap gap-1.5"
                                    >
                                        {dailyStatuses.map((status) => {
                                            const active = mark.status === status;

                                            return (
                                                <Button
                                                    key={status}
                                                    type="button"
                                                    size="sm"
                                                    variant={active ? 'default' : 'outline'}
                                                    aria-pressed={active}
                                                    onClick={() => change(index, { status })}
                                                    className={cn(
                                                        'flex-1 sm:flex-none',
                                                        active && pressedClass[status],
                                                    )}
                                                >
                                                    {statusMeta[status].label}
                                                </Button>
                                            );
                                        })}
                                    </div>
                                </div>
                                {away && (
                                    <TextInput
                                        aria-label={`Keterangan ${student.name}`}
                                        placeholder="Keterangan (opsional)"
                                        maxLength={255}
                                        value={mark.note}
                                        onChange={(event) => change(index, { note: event.target.value })}
                                    />
                                )}
                            </li>
                        );
                    })}
                </ul>
            </Panel>

            <div className="sticky bottom-0 mt-6 flex flex-wrap items-center justify-between gap-3 border border-border bg-card p-4 shadow-sm">
                <div className="flex flex-wrap gap-2">
                    {dailyStatuses.map((status) => (
                        <Badge key={status} variant={statusMeta[status].variant}>
                            {statusMeta[status].label}{' '}
                            {marks.filter((mark) => mark.status === status).length}
                        </Badge>
                    ))}
                </div>
                <Button
                    disabled={form.processing}
                    onClick={() => form.put(update.url(), { preserveScroll: true })}
                >
                    Simpan absensi
                </Button>
            </div>
        </>
    );
}
