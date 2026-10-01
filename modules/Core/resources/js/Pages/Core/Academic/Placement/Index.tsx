import { useState } from 'react';

import { DataTable, EmptyState, OptionSelect, Panel } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import { TableCell, TableRow } from '@shared/components/ui/table';

import MasterPage from '../../../../Components/MasterPage';
import type {
    AcademicYear,
    ClassGroup,
    SchoolSummary,
    Student,
} from '../../../../types/master';

const actions = [
    { value: 'promote', label: 'Naikkan kelas' },
    { value: 'move', label: 'Pindahkan rombel' },
    { value: 'graduate', label: 'Luluskan' },
];

/**
 * Penempatan Siswa: bulk tool to move students between rombel across
 * academic years. Shows a preview of the change before confirming.
 */
export default function PlacementIndex({
    school,
    years,
    classes,
    students,
}: {
    school: SchoolSummary;
    years: AcademicYear[];
    classes: ClassGroup[];
    students: Student[];
}) {
    const yearOptions = years.map((year) => ({
        value: String(year.id),
        label: year.name,
    }));
    const [fromYear, setFromYear] = useState('2');
    const [toYear, setToYear] = useState('3');
    const [fromClass, setFromClass] = useState(String(classes[0]?.id ?? ''));
    const [action, setAction] = useState('promote');
    const [targetClass, setTargetClass] = useState(String(classes[1]?.id ?? ''));
    const [selected, setSelected] = useState<number[]>([]);

    const roster = students.filter((student) => String(student.classId) === fromClass);
    const toggle = (id: number, checked: boolean) =>
        setSelected((current) =>
            checked ? [...current, id] : current.filter((item) => item !== id),
        );
    const target = classes.find((item) => String(item.id) === targetClass);
    const source = classes.find((item) => String(item.id) === fromClass);

    return (
        <MasterPage
            school={school}
            title="Penempatan Siswa"
            description="Naikkan, pindahkan, atau luluskan siswa secara massal saat pergantian tahun ajaran."
        >
            <div className="grid gap-6 lg:grid-cols-3">
                <Panel title="Pengaturan" className="lg:col-span-1">
                    <div className="flex flex-col gap-4">
                        <OptionSelect label="Dari tahun ajaran" value={fromYear} onChange={setFromYear} options={yearOptions} />
                        <OptionSelect label="Ke tahun ajaran" value={toYear} onChange={setToYear} options={yearOptions} />
                        <OptionSelect
                            label="Rombel asal"
                            value={fromClass}
                            onChange={(value) => {
                                setFromClass(value);
                                setSelected([]);
                            }}
                            options={classes.map((item) => ({ value: String(item.id), label: item.name }))}
                        />
                        <OptionSelect label="Tindakan" value={action} onChange={setAction} options={actions} />
                        {action !== 'graduate' && (
                            <OptionSelect
                                label="Rombel tujuan"
                                value={targetClass}
                                onChange={setTargetClass}
                                options={classes.map((item) => ({ value: String(item.id), label: item.name }))}
                            />
                        )}
                    </div>
                </Panel>

                <div className="flex flex-col gap-6 lg:col-span-2">
                    <Panel title={`Siswa ${source?.name ?? ''}`}>
                        {roster.length === 0 ? (
                            <EmptyState>Belum ada siswa di rombel ini.</EmptyState>
                        ) : (
                            <DataTable head={['', 'Nama', 'NIS']}>
                                {roster.map((student) => (
                                    <TableRow key={student.id}>
                                        <TableCell className="w-10">
                                            <Checkbox
                                                aria-label={`Pilih ${student.name}`}
                                                checked={selected.includes(student.id)}
                                                onCheckedChange={(checked) => toggle(student.id, checked === true)}
                                            />
                                        </TableCell>
                                        <TableCell className="font-medium">{student.name}</TableCell>
                                        <TableCell>{student.nis}</TableCell>
                                    </TableRow>
                                ))}
                            </DataTable>
                        )}
                    </Panel>

                    <Panel title="Pratinjau perubahan">
                        <p className="text-sm">
                            {selected.length === 0
                                ? 'Pilih siswa untuk melihat pratinjau.'
                                : action === 'graduate'
                                  ? `${selected.length} siswa dari ${source?.name} akan diluluskan.`
                                  : `${selected.length} siswa dari ${source?.name} akan ${action === 'promote' ? 'dinaikkan' : 'dipindahkan'} ke ${target?.name}.`}
                        </p>
                        <div className="mt-4 flex justify-end">
                            <Button disabled={selected.length === 0}>Konfirmasi penempatan</Button>
                        </div>
                    </Panel>
                </div>
            </div>
        </MasterPage>
    );
}
