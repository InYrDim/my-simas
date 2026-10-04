import { Link } from '@inertiajs/react';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';

import MyAttendanceController from '@/actions/Modules/Attendance/App/Http/Controllers/MyAttendanceController';
import { EmptyState, Panel } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';

import AttendancePage from '../../Components/AttendancePage';

interface MyAttendanceProps {
    student: { name: string; nis: string; class: string | null };
    month: {
        iso: string;
        label: string;
        previous: string;
        next: string | null;
    };
    totals: { status: string; label: string; count: number }[];
    days: {
        date: string;
        label: string;
        status: string;
        statusLabel: string;
        checkedIn: string | null;
        checkedOut: string | null;
        note: string | null;
    }[];
}

/** Absensi Saya: the student's own daily attendance, one month at a time. */
export default function MyAttendance({
    student,
    month,
    totals,
    days,
}: MyAttendanceProps) {
    const monthUrl = (iso: string) =>
        MyAttendanceController.url({ query: { bulan: iso } });

    return (
        <AttendancePage
            title="Absensi Saya"
            description={`${student.name} · ${student.class ?? 'belum ada kelas'}`}
            width="max-w-xl"
            actions={
                <div className="flex items-center gap-1">
                    <Button asChild variant="outline" size="icon">
                        <Link
                            href={monthUrl(month.previous)}
                            aria-label="Bulan sebelumnya"
                        >
                            <ChevronLeftIcon />
                        </Link>
                    </Button>
                    <span className="min-w-28 text-center text-sm text-foreground">
                        {month.label}
                    </span>
                    {month.next !== null ? (
                        <Button asChild variant="outline" size="icon">
                            <Link
                                href={monthUrl(month.next)}
                                aria-label="Bulan berikutnya"
                            >
                                <ChevronRightIcon />
                            </Link>
                        </Button>
                    ) : (
                        <Button
                            variant="outline"
                            size="icon"
                            disabled
                            aria-label="Bulan berikutnya"
                        >
                            <ChevronRightIcon />
                        </Button>
                    )}
                </div>
            }
        >
            <dl className="grid grid-cols-5 gap-2 text-center">
                {totals.map((total) => (
                    <div key={total.status}>
                        <dd className="text-lg font-semibold text-foreground">
                            {total.count}
                        </dd>
                        <dt className="text-xs text-muted-foreground">
                            {total.label}
                        </dt>
                    </div>
                ))}
            </dl>

            <div className="mt-8">
                {days.length === 0 ? (
                    <EmptyState>
                        Belum ada catatan absensi pada bulan ini.
                    </EmptyState>
                ) : (
                    <Panel>
                        <ul className="divide-y divide-border">
                            {days.map((day) => (
                                <li
                                    key={day.date}
                                    className="flex items-baseline justify-between gap-4 py-3"
                                >
                                    <div>
                                        <p className="text-sm text-foreground">
                                            {day.label}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {[
                                                day.checkedIn !== null
                                                    ? `Masuk ${day.checkedIn}`
                                                    : null,
                                                day.checkedOut !== null
                                                    ? `Pulang ${day.checkedOut}`
                                                    : null,
                                                day.note,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            day.status === 'present' ||
                                            day.status === 'late'
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {day.statusLabel}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    </Panel>
                )}
            </div>
        </AttendancePage>
    );
}
