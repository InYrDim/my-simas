import { useForm } from '@inertiajs/react';

import LessonRoll from './LessonRoll';
import type { RollStudent } from './LessonRoll';
import type { AttendanceStatus } from './status';

/**
 * The roll of one of the signed-in teacher's own lessons. The form sends
 * only the day, the slot and the marks: the class and the subject come
 * from the teacher's timetable on the server.
 */
export default function OwnLessonSheet({
    date,
    slotId,
    students,
    recorded,
    editable,
    updateUrl,
    lockedNote,
}: {
    date: string;
    slotId: string;
    students: RollStudent[];
    recorded: boolean;
    editable: boolean;
    updateUrl: string;
    lockedNote?: string;
}) {
    const form = useForm<{
        date: string;
        period_slot_id: string;
        marks: Record<number, AttendanceStatus | null>;
    }>({
        date,
        period_slot_id: slotId,
        marks: Object.fromEntries(
            students.map((student) => [student.id, student.status]),
        ),
    });
    const marks = form.data.marks;

    const save = () => {
        form.transform((data) => ({
            ...data,
            marks: Object.entries(data.marks)
                .filter(([, status]) => status !== null)
                .map(([student_id, status]) => ({
                    student_id: Number(student_id),
                    status,
                })),
        }));
        form.put(updateUrl, { preserveScroll: true });
    };

    return (
        <LessonRoll
            students={students}
            marks={marks}
            onMark={(id, status) =>
                form.setData('marks', { ...marks, [id]: status })
            }
            onFillPresent={() =>
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
            onSave={save}
            recorded={recorded}
            editable={editable}
            processing={form.processing}
            error={
                form.errors.period_slot_id ??
                form.errors.marks ??
                form.errors.date
            }
            lockedNote={lockedNote}
        />
    );
}
