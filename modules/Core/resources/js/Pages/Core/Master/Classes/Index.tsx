import { Link } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { useState } from 'react';

import { classShow } from '@/actions/Modules/Core/App/Http/Controllers/MasterDataController';
import {
    DataTable,
    EmptyState,
    OptionSelect,
} from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import FormDialog from '../../../../Components/FormDialog';
import { InputField, SelectField } from '../../../../Components/FormField';
import MasterPage from '../../../../Components/MasterPage';
import type {
    AcademicYear,
    ClassGroup,
    Grade,
    Major,
    SchoolSummary,
} from '../../../../types/master';

/**
 * Kelas / Rombel of one academic year, filterable by grade and major.
 */
export default function ClassesIndex({
    school,
    classes,
    grades,
    majors,
    years,
}: {
    school: SchoolSummary;
    classes: ClassGroup[];
    grades: Grade[];
    majors: Major[];
    years: AcademicYear[];
}) {
    const [year, setYear] = useState(
        String(years.find((item) => item.status === 'active')?.id ?? ''),
    );
    const [grade, setGrade] = useState('');
    const [major, setMajor] = useState('');

    const rows = classes.filter(
        (item) =>
            String(item.yearId) === year &&
            (grade === '' || String(item.gradeId) === grade) &&
            (major === '' || item.major === major),
    );

    return (
        <MasterPage
            school={school}
            title="Kelas"
            description="Rombongan belajar per tahun ajaran, lengkap dengan wali kelas dan ruangan."
            actions={
                <FormDialog
                    title="Tambah kelas"
                    trigger={
                        <Button>
                            <PlusIcon />
                            Tambah kelas
                        </Button>
                    }
                >
                    <InputField label="Nama kelas" id="name" placeholder="X IPA 1" />
                    <SelectField
                        label="Tingkat"
                        id="grade"
                        options={grades.map((item) => ({
                            value: String(item.id),
                            label: `Kelas ${item.name}`,
                        }))}
                    />
                </FormDialog>
            }
        >
            <div className="mb-4 grid gap-3 sm:grid-cols-3">
                <OptionSelect
                    label="Tahun ajaran"
                    value={year}
                    onChange={setYear}
                    options={years.map((item) => ({
                        value: String(item.id),
                        label: item.name,
                    }))}
                />
                <OptionSelect
                    label="Tingkat"
                    allLabel="Semua tingkat"
                    value={grade}
                    onChange={setGrade}
                    options={grades.map((item) => ({
                        value: String(item.id),
                        label: `Kelas ${item.name}`,
                    }))}
                />
                {school.hasMajors && (
                    <OptionSelect
                        label="Jurusan"
                        allLabel="Semua jurusan"
                        value={major}
                        onChange={setMajor}
                        options={majors.map((item) => ({
                            value: item.code,
                            label: item.code,
                        }))}
                    />
                )}
            </div>

            {rows.length === 0 ? (
                <EmptyState>Belum ada kelas untuk tahun ajaran ini.</EmptyState>
            ) : (
                <DataTable
                    head={['Kelas', school.homeroomLabel, 'Ruangan', 'Siswa']}
                >
                    {rows.map((item) => (
                        <TableRow key={item.id}>
                            <TableCell className="font-medium">
                                <Link
                                    href={classShow.url({ id: item.id })}
                                    className="hover:underline"
                                >
                                    {item.name}
                                </Link>
                            </TableCell>
                            <TableCell>{item.homeroom}</TableCell>
                            <TableCell>{item.room}</TableCell>
                            <TableCell>{item.students}</TableCell>
                        </TableRow>
                    ))}
                </DataTable>
            )}
        </MasterPage>
    );
}
