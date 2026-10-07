import { Link, router } from '@inertiajs/react';

import { index as inputPage } from '@/actions/Modules/Attendance/App/Http/Controllers/DailyInputController';
import overview from '@/actions/Modules/Attendance/App/Http/Controllers/OverviewController';
import { DataTable, EmptyState, StatCard } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';

import AttendancePage from '../../Components/AttendancePage';
import Filter from '../../Components/Filter';

interface ClassRow {
    id: number;
    name: string;
    homeroom: string | null;
    present: number;
    late: number;
    sick: number;
    permit: number;
    absent: number;
    pending: number;
    total: number;
    submitted: boolean;
}

interface OverviewProps {
    date: { iso: string; label: string; isToday: boolean };
    today: string;
    totals: {
        present: number;
        late: number;
        sick: number;
        permit: number;
        absent: number;
        pending: number;
        total: number;
    };
    classes: ClassRow[];
    can: { record: boolean };
}

/** Rekap Hari Ini: who is in school on one day and which classes still owe a roll call. */
export default function Overview({
    date,
    today,
    totals,
    classes,
    can,
}: OverviewProps) {
    const waiting = classes.filter((row) => row.pending > 0).length;

    return (
        <AttendancePage
            title={date.isToday ? 'Rekap Hari Ini' : 'Rekap Harian'}
            description={date.label}
            actions={
                can.record ? (
                    <Button asChild>
                        <Link
                            href={inputPage.url({
                                query: { tanggal: date.iso },
                            })}
                        >
                            Absensi gerbang
                        </Link>
                    </Button>
                ) : undefined
            }
            width="max-w-6xl"
        >
            <div className="mb-6">
                <Filter label="Tanggal" htmlFor="overview-date">
                    <Input
                        id="overview-date"
                        type="date"
                        value={date.iso}
                        max={today}
                        onChange={(event) =>
                            event.target.value !== '' &&
                            router.get(
                                overview.url({
                                    query: { tanggal: event.target.value },
                                }),
                            )
                        }
                    />
                </Filter>
            </div>

            <div className="grid grid-cols-2 gap-4 lg:grid-cols-6">
                <StatCard
                    label="Hadir"
                    value={totals.present}
                    hint={`dari ${totals.total} siswa`}
                />
                <StatCard label="Terlambat" value={totals.late} />
                <StatCard label="Sakit" value={totals.sick} />
                <StatCard label="Izin" value={totals.permit} />
                <StatCard label="Alpa" value={totals.absent} />
                <StatCard
                    label="Belum diabsen"
                    value={totals.pending}
                    hint={`${waiting} kelas`}
                />
            </div>

            <h2 className="mt-10 mb-3 text-sm font-semibold">Per kelas</h2>
            {classes.length === 0 ? (
                <EmptyState>
                    Belum ada kelas pada tahun ajaran aktif. Aktifkan tahun
                    ajaran dan buat kelas di Master Data lebih dulu.
                </EmptyState>
            ) : (
                <DataTable
                    head={[
                        'Kelas',
                        'Wali kelas',
                        'Hadir',
                        'Terlambat',
                        'Sakit',
                        'Izin',
                        'Alpa',
                        'Status',
                    ]}
                >
                    {classes.map((row) => (
                        <TableRow key={row.id}>
                            <TableCell className="font-medium">
                                {row.name}
                            </TableCell>
                            <TableCell className="text-muted-foreground">
                                {row.homeroom ?? '—'}
                            </TableCell>
                            <TableCell>
                                {row.present}/{row.total}
                            </TableCell>
                            <TableCell>{row.late}</TableCell>
                            <TableCell>{row.sick}</TableCell>
                            <TableCell>{row.permit}</TableCell>
                            <TableCell>{row.absent}</TableCell>
                            <TableCell>
                                {row.submitted ? (
                                    <Badge>Sudah diabsen</Badge>
                                ) : (
                                    <Badge variant="outline">
                                        {row.pending} belum diabsen
                                    </Badge>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </DataTable>
            )}
        </AttendancePage>
    );
}
