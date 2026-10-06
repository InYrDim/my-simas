import { EmptyState } from '@shared/components/page-parts';

import AttendancePage from '../../Components/AttendancePage';

interface MyClassTimetableProps {
    className: string | null;
    days: {
        day: string;
        dayNumber: number;
        lessons: {
            order: number;
            start: string;
            end: string;
            subject: string;
            teacher: string | null;
        }[];
    }[];
}

/** Kelas Saya › Jadwal Pelajaran: the student's class timetable by weekday. */
export default function MyClassTimetable({
    className,
    days,
}: MyClassTimetableProps) {
    return (
        <AttendancePage
            title="Jadwal Pelajaran"
            description={className ? `Kelas ${className}` : undefined}
            width="max-w-3xl"
        >
            {days.length === 0 ? (
                <EmptyState>
                    Belum ada jadwal pelajaran untuk kelas Anda. Admin sekolah
                    mengisinya di Akademik › Jadwal Pelajaran.
                </EmptyState>
            ) : (
                days.map((day) => (
                    <section key={day.dayNumber} className="mb-8">
                        <h2 className="text-xs font-medium text-muted-foreground">
                            {day.day}
                        </h2>
                        <ul className="mt-2 border-t border-border">
                            {day.lessons.map((lesson) => (
                                <li
                                    key={lesson.order}
                                    className="flex items-baseline justify-between gap-6 border-b border-border py-3"
                                >
                                    <span className="text-sm text-foreground">
                                        <span className="mr-3 font-mono text-xs text-muted-foreground">
                                            {lesson.start}–{lesson.end}
                                        </span>
                                        {lesson.subject}
                                    </span>
                                    <span className="text-right text-xs text-muted-foreground">
                                        {lesson.teacher ?? '—'} · jam ke-
                                        {lesson.order}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))
            )}
        </AttendancePage>
    );
}
