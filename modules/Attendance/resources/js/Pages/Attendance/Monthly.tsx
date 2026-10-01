import { useState } from 'react';

import { DataTable, OptionSelect } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import AttendancePage from '../../Components/AttendancePage';

interface Row {
    id: number;
    name: string;
    nis: string;
    present: number;
    sick: number;
    permit: number;
    absent: number;
    percent: number;
}

interface MonthlyProps {
    month: { iso: string; label: string };
    classes: { value: string; label: string }[];
    rows: Row[];
}

/** Rekap Bulanan: each student's month as counts and a presence rate. */
export default function Monthly({ month, classes, rows }: MonthlyProps) {
    const [classId, setClassId] = useState(classes[0]?.value ?? '');

    return (
        <AttendancePage
            title="Rekap Bulanan"
            description={month.label}
            actions={<Button variant="outline">Unduh rekap</Button>}
            width="max-w-5xl"
        >
            <div className="mb-6 max-w-xs">
                <OptionSelect label="Kelas" value={classId} onChange={setClassId} options={classes} />
            </div>

            <DataTable head={['Siswa', 'NIS', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Kehadiran']}>
                {rows.map((row) => (
                    <TableRow key={row.id}>
                        <TableCell className="font-medium">{row.name}</TableCell>
                        <TableCell className="font-mono text-xs">{row.nis}</TableCell>
                        <TableCell>{row.present}</TableCell>
                        <TableCell>{row.sick}</TableCell>
                        <TableCell>{row.permit}</TableCell>
                        <TableCell>{row.absent}</TableCell>
                        <TableCell>
                            <Badge variant={row.percent >= 90 ? 'default' : row.percent >= 75 ? 'outline' : 'destructive'}>
                                {row.percent}%
                            </Badge>
                        </TableCell>
                    </TableRow>
                ))}
            </DataTable>
        </AttendancePage>
    );
}
