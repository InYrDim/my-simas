import { Link, router } from '@inertiajs/react';

import { index as classRoll } from '@/actions/Modules/Attendance/App/Http/Controllers/ClassAttendanceController';
import { index as history } from '@/actions/Modules/Attendance/App/Http/Controllers/HistoryController';
import { update as check } from '@/actions/Modules/Attendance/App/Http/Controllers/LessonCheckController';
import { EmptyState, Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@shared/components/ui/tabs';

import AttendancePage from '../../Components/AttendancePage';
import { lessonStateMeta } from '../../Components/status';
import type { LessonState } from '../../Components/status';

interface Lesson {
    slotId: number;
    order: number;
    startsAt: string;
    endsAt: string;
    className: string;
    subjectName: string;
    state: LessonState;
    recorded: boolean;
    checked: boolean;
}

interface WeekDay {
    day: string;
    dayNumber: number;
    lessons: {
        order: number;
        startsAt: string;
        endsAt: string;
        className: string;
        subjectName: string;
    }[];
}

interface MyScheduleProps {
    date: { iso: string; label: string };
    banner: 'none' | LessonState;
    lessons: Lesson[];
    week: WeekDay[];
}

/** The banner that follows the teaching range of the day. */
const banners: Record<LessonState, string> = {
    upcoming: 'Jadwal Mengajar Hari Ini! Sebagai Berikut',
    running: 'Pembelajaran Sedang Berlangsung',
    finished: 'Sudah Selesai Jadwal Mengajar Hari ini',
};

/**
 * Saya › Jadwal Saya: the teacher's lessons in one place. "Hari Ini" is
 * a todo list: a card is ticked off after its hour, automatically when
 * the attendance is saved, and the banner follows the day's teaching
 * range. "Minggu Ini" is the whole week by weekday.
 */
export default function MySchedule({
    date,
    banner,
    lessons,
    week,
}: MyScheduleProps) {
    return (
        <AttendancePage
            title="Jadwal Saya"
            description={date.label}
            width="max-w-3xl"
        >
            <Tabs defaultValue="today">
                <TabsList className="mb-6">
                    <TabsTrigger value="today">Hari Ini</TabsTrigger>
                    <TabsTrigger value="week">Minggu Ini</TabsTrigger>
                </TabsList>

                <TabsContent value="today">
                    {banner === 'none' ? (
                        <EmptyState>
                            Tidak ada jadwal mengajar hari ini. Lihat tab Minggu
                            Ini untuk jadwal sepekan.
                        </EmptyState>
                    ) : (
                        <>
                            <Alert className="mb-6">
                                <AlertDescription>
                                    {banners[banner]}
                                </AlertDescription>
                            </Alert>

                            <Panel>
                                <ul className="divide-y divide-border">
                                    {lessons.map((lesson) => (
                                        <li
                                            key={lesson.slotId}
                                            className="flex items-start gap-3 py-3 first:pt-0 last:pb-0"
                                        >
                                            <Checkbox
                                                aria-label={`Tandai ${lesson.subjectName} di kelas ${lesson.className} selesai`}
                                                className="mt-0.5"
                                                checked={lesson.checked}
                                                disabled={
                                                    !lesson.checked &&
                                                    lesson.state !== 'finished'
                                                }
                                                onCheckedChange={(checked) =>
                                                    router.put(
                                                        check.url(),
                                                        {
                                                            period_slot_id:
                                                                lesson.slotId,
                                                            checked:
                                                                checked ===
                                                                true,
                                                        },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            />
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium text-foreground">
                                                    {lesson.subjectName} · Kelas{' '}
                                                    {lesson.className}
                                                </p>
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    Jam ke-{lesson.order} ·{' '}
                                                    {lesson.startsAt}–
                                                    {lesson.endsAt}
                                                </p>
                                                <div className="mt-2 flex flex-wrap gap-1.5">
                                                    <Badge
                                                        variant={
                                                            lessonStateMeta[
                                                                lesson.state
                                                            ].variant
                                                        }
                                                    >
                                                        {
                                                            lessonStateMeta[
                                                                lesson.state
                                                            ].label
                                                        }
                                                    </Badge>
                                                    {lesson.recorded && (
                                                        <Badge variant="secondary">
                                                            Sudah diabsen
                                                        </Badge>
                                                    )}
                                                </div>
                                            </div>
                                            {lesson.state === 'running' && (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={classRoll.url({
                                                            query: {
                                                                jam: String(
                                                                    lesson.slotId,
                                                                ),
                                                            },
                                                        })}
                                                    >
                                                        Isi absensi
                                                    </Link>
                                                </Button>
                                            )}
                                            {lesson.state === 'finished' && (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={history.url({
                                                            query: {
                                                                tanggal:
                                                                    date.iso,
                                                                jam: String(
                                                                    lesson.slotId,
                                                                ),
                                                            },
                                                        })}
                                                    >
                                                        Riwayat
                                                    </Link>
                                                </Button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </Panel>
                        </>
                    )}
                </TabsContent>

                <TabsContent value="week">
                    {week.length === 0 ? (
                        <EmptyState>
                            Belum ada jadwal mengajar untuk Anda. Admin sekolah
                            mengisinya di Akademik › Jadwal Pelajaran, setelah
                            pengampu mapel diatur.
                        </EmptyState>
                    ) : (
                        <div className="flex flex-col gap-6">
                            {week.map((day) => (
                                <Panel key={day.dayNumber} title={day.day}>
                                    <ul className="divide-y divide-border">
                                        {day.lessons.map((lesson) => (
                                            <li
                                                key={`${lesson.order}-${lesson.className}`}
                                                className="flex items-baseline justify-between gap-6 py-3 first:pt-0 last:pb-0"
                                            >
                                                <span className="text-sm text-foreground">
                                                    <span className="mr-3 font-mono text-xs text-muted-foreground">
                                                        {lesson.startsAt}–
                                                        {lesson.endsAt}
                                                    </span>
                                                    {lesson.subjectName}
                                                </span>
                                                <span className="text-right text-xs text-muted-foreground">
                                                    Kelas {lesson.className} ·
                                                    jam ke-{lesson.order}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </Panel>
                            ))}
                        </div>
                    )}
                </TabsContent>
            </Tabs>
        </AttendancePage>
    );
}
