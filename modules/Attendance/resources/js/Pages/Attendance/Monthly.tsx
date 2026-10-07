import { Link, router } from '@inertiajs/react';

import monthly from '@/actions/Modules/Attendance/App/Http/Controllers/MonthlyRecapController';
import { DataTable, EmptyState } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Input } from '@shared/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@shared/components/ui/tabs';
import { TableCell, TableRow } from '@shared/components/ui/table';

import AttendancePage from '../../Components/AttendancePage';
import ClassSelect from '../../Components/ClassSelect';
import Filter from '../../Components/Filter';
import type { ClassOption } from '../../Components/status';

interface Row {
    id: number;
    name: string;
    nis: string;
    present: number;
    late: number;
    sick: number;
    permit: number;
    absent: number;
    days: number;
    percent: number | null;
}

type Source = 'semua' | 'gerbang' | 'pelajaran';

interface MonthlyProps {
    source: Source;
    month: { iso: string; label: string };
    currentMonth: string;
    classes: ClassOption[];
    classId: string;
    rows: Row[];
}

/** Rekap Bulanan: each student's month as counts and a presence rate. */
export default function Monthly({
    source,
    month,
    currentMonth,
    classes,
    classId,
    rows,
}: MonthlyProps) {
    const open = (kelas: string, bulan: string) =>
        router.get(monthly.url({ query: { kelas, bulan, jenis: source } }));

    const tabUrl = (jenis: Source) =>
        monthly.url({ query: { kelas: classId, bulan: month.iso, jenis } });

    return (
        <AttendancePage
            title="Rekap Bulanan"
            description={month.label}
            width="max-w-5xl"
        >
            <Tabs value={source} className="mb-6">
                <TabsList>
                    <TabsTrigger value="semua" asChild>
                        <Link href={tabUrl('semua')}>Semua</Link>
                    </TabsTrigger>
                    <TabsTrigger value="gerbang" asChild>
                        <Link href={tabUrl('gerbang')}>Rekap Gerbang</Link>
                    </TabsTrigger>
                    <TabsTrigger value="pelajaran" asChild>
                        <Link href={tabUrl('pelajaran')}>
                            Rekap Jam Pelajaran
                        </Link>
                    </TabsTrigger>
                </TabsList>
            </Tabs>

            <div className="mb-6 flex flex-col gap-4 sm:flex-row">
                <Filter label="Kelas">
                    <ClassSelect
                        value={classId}
                        onChange={(value) => open(value, month.iso)}
                        options={classes}
                    />
                </Filter>
                <Filter label="Bulan" htmlFor="monthly-month">
                    <Input
                        id="monthly-month"
                        type="month"
                        value={month.iso}
                        max={currentMonth}
                        onChange={(event) =>
                            event.target.value !== '' &&
                            open(classId, event.target.value)
                        }
                    />
                </Filter>
            </div>

            {classId === '' ? (
                <EmptyState>
                    Belum ada kelas pada tahun ajaran aktif. Aktifkan tahun
                    ajaran dan buat kelas di Master Data lebih dulu.
                </EmptyState>
            ) : rows.length === 0 ? (
                <EmptyState>Belum ada siswa aktif di kelas ini.</EmptyState>
            ) : (
                <>
                    <DataTable
                        head={[
                            'Siswa',
                            'NIS',
                            'Hadir',
                            'Terlambat',
                            'Sakit',
                            'Izin',
                            'Alpa',
                            'Kehadiran',
                        ]}
                    >
                        {rows.map((row) => (
                            <TableRow key={row.id}>
                                <TableCell className="font-medium">
                                    {row.name}
                                </TableCell>
                                <TableCell className="font-mono text-xs">
                                    {row.nis}
                                </TableCell>
                                <TableCell>{row.present}</TableCell>
                                <TableCell>{row.late}</TableCell>
                                <TableCell>{row.sick}</TableCell>
                                <TableCell>{row.permit}</TableCell>
                                <TableCell>{row.absent}</TableCell>
                                <TableCell>
                                    {row.percent === null ? (
                                        <span className="text-muted-foreground">
                                            —
                                        </span>
                                    ) : (
                                        <Badge
                                            variant={
                                                row.percent >= 90
                                                    ? 'default'
                                                    : row.percent >= 75
                                                      ? 'outline'
                                                      : 'destructive'
                                            }
                                        >
                                            {row.percent}%
                                        </Badge>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </DataTable>
                    <p className="mt-3 text-sm text-muted-foreground">
                        {source === 'gerbang'
                            ? 'Kehadiran dihitung dari hari yang sudah diabsen di gerbang'
                            : source === 'pelajaran'
                              ? 'Kehadiran dihitung dari jam pelajaran yang sudah diabsen'
                              : 'Kehadiran dihitung dari absensi gerbang dan jam pelajaran yang sudah diabsen'}
                        : hadir dan terlambat dibagi seluruh catatan siswa pada
                        bulan ini. Unduhan ada di Statistik &amp; Laporan.
                    </p>
                </>
            )}
        </AttendancePage>
    );
}
