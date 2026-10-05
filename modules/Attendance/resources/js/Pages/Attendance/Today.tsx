import { Link, router } from '@inertiajs/react';

import { index as classRoll } from '@/actions/Modules/Attendance/App/Http/Controllers/ClassAttendanceController';
import { index as history } from '@/actions/Modules/Attendance/App/Http/Controllers/HistoryController';
import { update as check } from '@/actions/Modules/Attendance/App/Http/Controllers/LessonCheckController';
import { EmptyState, Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';

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

interface TodayProps {
    date: { iso: string; label: string };
    banner: 'none' | LessonState;
    lessons: Lesson[];
}

/** The banner that follows the teaching range of the day. */
const banners: Record<LessonState, string> = {
    upcoming: 'Jadwal Mengajar Hari Ini! Sebagai Berikut',
    running: 'Pembelajaran Sedang Berlangsung',
    finished: 'Sudah Selesai Jadwal Mengajar Hari ini',
};

/**
 * Kelas Saya › Jadwal Hari Ini: today's lessons as a todo list. A card
 * is ticked off after its hour, automatically when the attendance is
 * saved; the banner follows the day's teaching range.
 */
export default function Today({ date, banner, lessons }: TodayProps) {
    return (
        <AttendancePage
            title="Jadwal Hari Ini"
            description={date.label}
            width="max-w-3xl"
        >
            {banner === 'none' ? (
                <EmptyState>
                    Tidak ada jadwal mengajar hari ini. Jadwal mengajar lengkap
                    ada di menu Jadwal Mengajar.
                </EmptyState>
            ) : (
                <>
                    <Alert className="mb-6">
                        <AlertDescription>{banners[banner]}</AlertDescription>
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
                                                    checked: checked === true,
                                                },
                                                { preserveScroll: true },
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
                                            {lesson.startsAt}–{lesson.endsAt}
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
                                                        tanggal: date.iso,
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
        </AttendancePage>
    );
}
