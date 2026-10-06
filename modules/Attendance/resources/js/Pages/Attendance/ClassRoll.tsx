import { Link } from '@inertiajs/react';

import {
    index,
    update,
} from '@/actions/Modules/Attendance/App/Http/Controllers/ClassAttendanceController';
import { EmptyState } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';

import AttendancePage from '../../Components/AttendancePage';
import ClassRollTabs from '../../Components/ClassRollTabs';
import OwnLessonSheet from '../../Components/OwnLessonSheet';
import type { RollStudent } from '../../Components/LessonRoll';
import { lessonStateMeta } from '../../Components/status';
import type { AttendanceStatus, LessonState } from '../../Components/status';

interface Lesson {
    slotId: number;
    order: number;
    startsAt: string;
    endsAt: string;
    className: string;
    subjectName: string;
    state: LessonState;
    recorded: boolean;
}

interface ClassRollProps {
    date: { iso: string; label: string };
    lessons: Lesson[];
    slotId: string;
    selected: Lesson | null;
    editable: boolean;
    recorded: boolean;
    previous: { label: string; marks: Record<number, AttendanceStatus> } | null;
    students: RollStudent[];
}

/**
 * Kelas Saya › Absensi Kelas: today's lessons, with the one in its own
 * hour open automatically. Outside the hour the roll is read-only — a
 * finished record is corrected on Riwayat Absensi.
 */
export default function ClassRoll({
    date,
    lessons,
    slotId,
    selected,
    editable,
    recorded,
    previous,
    students,
}: ClassRollProps) {
    return (
        <AttendancePage
            title="Absensi Kelas"
            description={date.label}
            width="max-w-3xl"
        >
            <ClassRollTabs current="fill" />

            {lessons.length === 0 ? (
                <EmptyState>
                    Tidak ada jadwal mengajar hari ini. Absensi jam pelajaran
                    dibuka otomatis saat jamnya berlangsung.
                </EmptyState>
            ) : (
                <>
                    <div className="mb-6 flex flex-wrap gap-2">
                        {lessons.map((lesson) => (
                            <Button
                                key={lesson.slotId}
                                asChild
                                size="sm"
                                variant={
                                    lesson.slotId === Number(slotId)
                                        ? 'default'
                                        : 'outline'
                                }
                            >
                                <Link
                                    href={index.url({
                                        query: {
                                            jam: String(lesson.slotId),
                                        },
                                    })}
                                    preserveScroll
                                >
                                    Jam ke-{lesson.order} · {lesson.className}
                                </Link>
                            </Button>
                        ))}
                    </div>

                    {selected !== null && (
                        <>
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <h2 className="text-base font-semibold text-foreground">
                                        {selected.subjectName} · Kelas{' '}
                                        {selected.className}
                                    </h2>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Jam ke-{selected.order} ·{' '}
                                        {selected.startsAt}–{selected.endsAt}
                                    </p>
                                </div>
                                <Badge
                                    variant={
                                        lessonStateMeta[selected.state].variant
                                    }
                                >
                                    {lessonStateMeta[selected.state].label}
                                </Badge>
                            </div>

                            <OwnLessonSheet
                                key={`${date.iso}-${selected.slotId}`}
                                date={date.iso}
                                slotId={String(selected.slotId)}
                                students={students}
                                recorded={recorded}
                                previous={previous}
                                editable={editable}
                                updateUrl={update.url()}
                                lockedNote="Di luar jam pelajaran, absensi tidak dapat diisi di sini. Ubah data lewat tab Koreksi."
                            />
                        </>
                    )}
                </>
            )}
        </AttendancePage>
    );
}
