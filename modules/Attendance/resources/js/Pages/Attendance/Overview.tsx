import { Link } from '@inertiajs/react';

import { DataTable, StatCard } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import AttendancePage from '../../Components/AttendancePage';

interface ClassRow {
    id: number;
    name: string;
    homeroom: string;
    present: number;
    sick: number;
    permit: number;
    absent: number;
    total: number;
    submitted: boolean;
}

interface OverviewProps {
    date: { iso: string; label: string };
    totals: {
        present: number;
        sick: number;
        permit: number;
        absent: number;
        pending: number;
        total: number;
    };
    classes: ClassRow[];
}

/** Rekap Hari Ini: who is in school today and which classes still owe a roll call. */
export default function Overview({ date, totals, classes }: OverviewProps) {
    const waiting = classes.filter((row) => !row.submitted).length;

    return (
        <AttendancePage
            title="Rekap Hari Ini"
            description={date.label}
            actions={
                <Button asChild>
                    <Link href="/absensi/input">Input absensi</Link>
                </Button>
            }
            width="max-w-6xl"
        >
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
                <StatCard label="Hadir" value={totals.present} hint={`dari ${totals.total} siswa`} />
                <StatCard label="Sakit" value={totals.sick} />
                <StatCard label="Izin" value={totals.permit} />
                <StatCard label="Alpa" value={totals.absent} />
                <StatCard label="Belum diabsen" value={totals.pending} hint={`${waiting} kelas`} />
            </div>

            <h2 className="mt-10 mb-3 text-sm font-semibold">Per kelas</h2>
            <DataTable head={['Kelas', 'Wali kelas', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Status']}>
                {classes.map((row) => (
                    <TableRow key={row.id}>
                        <TableCell className="font-medium">{row.name}</TableCell>
                        <TableCell className="text-muted-foreground">{row.homeroom}</TableCell>
                        <TableCell>
                            {row.submitted ? `${row.present}/${row.total}` : '—'}
                        </TableCell>
                        <TableCell>{row.submitted ? row.sick : '—'}</TableCell>
                        <TableCell>{row.submitted ? row.permit : '—'}</TableCell>
                        <TableCell>{row.submitted ? row.absent : '—'}</TableCell>
                        <TableCell>
                            {row.submitted ? (
                                <Badge>Sudah diabsen</Badge>
                            ) : (
                                <Badge variant="outline">Belum diabsen</Badge>
                            )}
                        </TableCell>
                    </TableRow>
                ))}
            </DataTable>
        </AttendancePage>
    );
}
