import { router, useForm } from '@inertiajs/react';

import {
    index,
    update,
} from '@/actions/Modules/Attendance/App/Http/Controllers/LessonAttendanceController';
import { EmptyState, OptionSelect, Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { cn } from '@shared/lib/utils';

import AttendancePage from '../../Components/AttendancePage';
import ClassSelect from '../../Components/ClassSelect';
import Filter from '../../Components/Filter';
import {
    lessonStatuses,
    pressedClass,
    statusMeta,
} from '../../Components/status';
import type { AttendanceStatus, ClassOption } from '../../Components/status';

interface Student {
    id: number;
    name: string;
    nis: string;
    status: AttendanceStatus | null;
    scanned: boolean;
    daily: AttendanceStatus | null;
}

interface Option {
    value: string;
    label: string;
}

interface LessonsProps {
    date: { iso: string; label: string; isToday: boolean };
    today: string;
    classes: ClassOption[];
    classId: string;
    slots: Option[];
    slotId: string;
    subjects: Option[];
    subjectId: string;
    recorded: boolean;
    students: Student[];
}

/**
 * Absensi Pelajaran: one class in one lesson of one day. Students who
 * are sick, excused or absent for the day start that way; the rest wait to
 * be marked.
 */
export default function Lessons({
    date,
    today,
    classes,
    classId,
    slots,
    slotId,
    subjects,
    subjectId,
    recorded,
    students,
}: LessonsProps) {
    const open = (query: { kelas: string; tanggal: string; jam?: string }) =>
        router.get(index.url({ query }));

    return (
        <AttendancePage
            title="Absensi Pelajaran"
            description={`Per jam pelajaran di kelas · ${date.label}`}
            width="max-w-3xl"
        >
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:flex-wrap">
                <Filter label="Kelas">
                    <ClassSelect
                        value={classId}
                        onChange={(kelas) =>
                            open({ kelas, tanggal: date.iso, jam: slotId })
                        }
                        options={classes}
                    />
                </Filter>
                <Filter label="Tanggal" htmlFor="lesson-date">
                    <Input
                        id="lesson-date"
                        type="date"
                        value={date.iso}
                        max={today}
                        onChange={(event) =>
                            event.target.value !== '' &&
                            open({
                                kelas: classId,
                                tanggal: event.target.value,
                            })
                        }
                    />
                </Filter>
                {slots.length > 0 && (
                    <Filter label="Jam pelajaran">
                        <OptionSelect
                            label="Jam pelajaran"
                            value={slotId}
                            onChange={(jam) =>
                                open({ kelas: classId, tanggal: date.iso, jam })
                            }
                            options={slots}
                        />
                    </Filter>
                )}
            </div>

            {classId === '' ? (
                <EmptyState>
                    Belum ada kelas pada tahun ajaran aktif. Aktifkan tahun
                    ajaran dan buat kelas di Master Data lebih dulu.
                </EmptyState>
            ) : slots.length === 0 ? (
                <EmptyState>
                    Tidak ada jam pelajaran pada hari ini. Atur jam pelajaran di
                    Akademik › Jam Pelajaran.
                </EmptyState>
            ) : students.length === 0 ? (
                <EmptyState>Belum ada siswa aktif di kelas ini.</EmptyState>
            ) : (
                // A new class, day or lesson is a new form.
                <LessonRoll
                    key={`${classId}-${date.iso}-${slotId}`}
                    classId={classId}
                    date={date.iso}
                    slotId={slotId}
                    subjects={subjects}
                    subjectId={subjectId}
                    recorded={recorded}
                    students={students}
                />
            )}
        </AttendancePage>
    );
}

const NO_SUBJECT = 'none';

function LessonRoll({
    classId,
    date,
    slotId,
    subjects,
    subjectId,
    recorded,
    students,
}: {
    classId: string;
    date: string;
    slotId: string;
    subjects: Option[];
    subjectId: string;
    recorded: boolean;
    students: Student[];
}) {
    const form = useForm<{
        class_id: string;
        date: string;
        period_slot_id: string;
        subject_id: string;
        marks: Record<number, AttendanceStatus | null>;
    }>({
        class_id: classId,
        date,
        period_slot_id: slotId,
        subject_id: subjectId === '' ? NO_SUBJECT : subjectId,
        marks: Object.fromEntries(
            students.map((student) => [student.id, student.status]),
        ),
    });
    const marks = form.data.marks;
    const unmarked = students.filter(
        (student) => marks[student.id] === null,
    ).length;

    const mark = (id: number, status: AttendanceStatus) =>
        form.setData('marks', { ...marks, [id]: status });

    const save = () => {
        form.transform((data) => ({
            ...data,
            subject_id: data.subject_id === NO_SUBJECT ? null : data.subject_id,
            marks: Object.entries(data.marks)
                .filter(([, status]) => status !== null)
                .map(([student_id, status]) => ({
                    student_id: Number(student_id),
                    status,
                })),
        }));
        form.put(update.url(), { preserveScroll: true });
    };

    const error =
        form.errors.marks ??
        form.errors.period_slot_id ??
        form.errors.subject_id ??
        form.errors.date ??
        form.errors.class_id;

    return (
        <>
            {error !== undefined && (
                <Alert variant="destructive" className="mb-6">
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <Filter label="Mata pelajaran">
                    <OptionSelect
                        label="Mata pelajaran"
                        value={form.data.subject_id}
                        onChange={(value) => form.setData('subject_id', value)}
                        options={[
                            { value: NO_SUBJECT, label: 'Tidak dicatat' },
                            ...subjects,
                        ]}
                    />
                </Filter>
                <Button
                    type="button"
                    variant="outline"
                    disabled={unmarked === 0}
                    onClick={() =>
                        form.setData(
                            'marks',
                            Object.fromEntries(
                                students.map((student) => [
                                    student.id,
                                    marks[student.id] ?? 'present',
                                ]),
                            ),
                        )
                    }
                >
                    Tandai sisanya hadir
                </Button>
            </div>

            <Panel>
                <ul className="flex flex-col divide-y divide-border">
                    {students.map((student) => (
                        <li
                            key={student.id}
                            className="flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium">
                                    {student.name}
                                </p>
                                <p className="font-mono text-xs text-muted-foreground">
                                    {student.nis}
                                    {student.scanned && ' · dipindai'}
                                    {student.daily !== null &&
                                        ` · hari ini ${statusMeta[student.daily].label.toLowerCase()}`}
                                </p>
                            </div>
                            <div
                                role="group"
                                aria-label={`Status ${student.name}`}
                                className="flex flex-wrap gap-1.5"
                            >
                                {lessonStatuses.map((status) => {
                                    const active = marks[student.id] === status;

                                    return (
                                        <Button
                                            key={status}
                                            type="button"
                                            size="sm"
                                            variant={
                                                active ? 'default' : 'outline'
                                            }
                                            aria-pressed={active}
                                            onClick={() =>
                                                mark(student.id, status)
                                            }
                                            className={cn(
                                                'min-w-16 flex-1 sm:flex-none',
                                                active && pressedClass[status],
                                            )}
                                        >
                                            {statusMeta[status].label}
                                        </Button>
                                    );
                                })}
                            </div>
                        </li>
                    ))}
                </ul>
            </Panel>

            <div className="sticky bottom-0 mt-6 flex flex-wrap items-center justify-between gap-3 border border-border bg-card p-4 shadow-sm">
                <div className="flex flex-wrap gap-2">
                    {lessonStatuses.map((status) => (
                        <Badge
                            key={status}
                            variant={statusMeta[status].variant}
                        >
                            {statusMeta[status].label}{' '}
                            {
                                students.filter(
                                    (student) => marks[student.id] === status,
                                ).length
                            }
                        </Badge>
                    ))}
                    {unmarked > 0 && (
                        <Badge variant="outline">
                            Belum ditandai {unmarked}
                        </Badge>
                    )}
                </div>
                <div className="flex items-center gap-3">
                    {recorded && (
                        <span className="text-sm text-muted-foreground">
                            Sudah pernah disimpan
                        </span>
                    )}
                    <Button
                        disabled={
                            form.processing || unmarked === students.length
                        }
                        onClick={save}
                    >
                        Simpan absensi
                    </Button>
                </div>
            </div>
        </>
    );
}
