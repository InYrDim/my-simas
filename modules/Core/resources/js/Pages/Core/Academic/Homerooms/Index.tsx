import { useState } from 'react';

import { DataTable } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';
import { OptionSelect } from '@shared/components/page-parts';

import MasterPage from '../../../../Components/MasterPage';
import type { ClassGroup, SchoolSummary, Teacher } from '../../../../types/master';

/**
 * Wali Kelas: set the homeroom teacher of every rombel in one sweep. A
 * teacher holding more than one class is flagged, not blocked.
 */
export default function HomeroomsIndex({
    school,
    classes,
    teachers,
}: {
    school: SchoolSummary;
    classes: ClassGroup[];
    teachers: Teacher[];
}) {
    const [chosen, setChosen] = useState<Record<number, string>>(() =>
        Object.fromEntries(classes.map((group) => [group.id, String(group.homeroomId)])),
    );
    const [dirty, setDirty] = useState(false);

    const options = teachers.map((teacher) => ({
        value: String(teacher.id),
        label: teacher.name,
    }));
    const load = (teacherId: string) =>
        Object.values(chosen).filter((value) => value === teacherId).length;

    return (
        <MasterPage
            school={school}
            title={school.homeroomLabel}
            description={`Tetapkan ${school.homeroomLabel.toLowerCase()} untuk tiap rombel tahun ajaran aktif.`}
            width="max-w-5xl"
            actions={
                <Button disabled={!dirty} onClick={() => setDirty(false)}>
                    Simpan penetapan
                </Button>
            }
        >
            <DataTable head={['Kelas', 'Ruangan', school.homeroomLabel, '']}>
                {classes.map((group) => (
                    <TableRow key={group.id}>
                        <TableCell className="font-medium">{group.name}</TableCell>
                        <TableCell>{group.room}</TableCell>
                        <TableCell className="w-80">
                            <OptionSelect
                                label={`${school.homeroomLabel} ${group.name}`}
                                value={chosen[group.id]}
                                onChange={(value) => {
                                    setChosen((current) => ({ ...current, [group.id]: value }));
                                    setDirty(true);
                                }}
                                options={options}
                            />
                        </TableCell>
                        <TableCell>
                            {load(chosen[group.id]) > 1 && (
                                <Badge variant="outline">
                                    merangkap {load(chosen[group.id])} kelas
                                </Badge>
                            )}
                        </TableCell>
                    </TableRow>
                ))}
            </DataTable>
        </MasterPage>
    );
}
