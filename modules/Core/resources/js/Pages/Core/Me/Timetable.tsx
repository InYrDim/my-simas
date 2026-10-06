import { Head } from '@inertiajs/react';

import TenantShell from '@shared/components/TenantShell';

interface TimetableProps {
    school: { name: string; slug: string };
    days: {
        day: string;
        dayNumber: number;
        lessons: {
            order: number;
            start: string;
            end: string;
            class: string;
            subject: string;
        }[];
    }[];
}

/** Jadwal Mengajar: the teacher's own lessons of the active year, by weekday. */
export default function Timetable({ school, days }: TimetableProps) {
    return (
        <TenantShell width="max-w-xl">
            <Head title="Jadwal Mengajar" />

            <h1 className="text-xl leading-7 font-semibold text-foreground">
                Jadwal Mengajar
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">
                Tahun ajaran aktif · {school.name}
            </p>

            {days.length === 0 ? (
                <p className="mt-8 text-sm text-muted-foreground">
                    Belum ada jadwal mengajar untuk Anda. Admin sekolah
                    mengisinya di Akademik › Jadwal Pelajaran, setelah pengampu
                    mapel diatur.
                </p>
            ) : (
                days.map((day) => (
                    <section key={day.dayNumber} className="mt-8">
                        <h2 className="text-xs font-medium text-muted-foreground">
                            {day.day}
                        </h2>
                        <ul className="mt-2 border-t border-border">
                            {day.lessons.map((lesson) => (
                                <li
                                    key={`${lesson.order}-${lesson.class}`}
                                    className="flex items-baseline justify-between gap-6 border-b border-border py-3"
                                >
                                    <span className="text-sm text-foreground">
                                        <span className="mr-3 font-mono text-xs text-muted-foreground">
                                            {lesson.start}–{lesson.end}
                                        </span>
                                        {lesson.subject}
                                    </span>
                                    <span className="text-right text-xs text-muted-foreground">
                                        Kelas {lesson.class} · jam ke-
                                        {lesson.order}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))
            )}
        </TenantShell>
    );
}
