import { Link } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { useState } from 'react';

import {
    destroy,
    show,
    store,
    update,
} from '@/actions/Modules/Core/App/Http/Controllers/ClassGroupController';
import {
    DataTable,
    EmptyState,
    OptionSelect,
} from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import ConfirmAction from '../../../../Components/ConfirmAction';
import FormDialog from '../../../../Components/FormDialog';
import { InputField, SelectField } from '../../../../Components/FormField';
import MasterPage from '../../../../Components/MasterPage';
import type {
    AcademicYear,
    ClassGroup,
    Grade,
    Major,
    Room,
    SchoolSummary,
} from '../../../../types/master';

function ClassForm({
    group,
    school,
    years,
    grades,
    majors,
    rooms,
    defaultYear,
}: {
    group?: ClassGroup;
    school: SchoolSummary;
    years: AcademicYear[];
    grades: Grade[];
    majors: Major[];
    rooms: Room[];
    defaultYear: string;
}) {
    return (
        <>
            <InputField
                label="Nama kelas"
                id="name"
                placeholder="X IPA 1"
                defaultValue={group?.name}
            />
            <SelectField
                label="Tahun ajaran"
                id="academic_year_id"
                options={years.map((item) => ({
                    value: String(item.id),
                    label: item.name,
                }))}
                defaultValue={String(group?.yearId ?? defaultYear)}
            />
            <SelectField
                label="Tingkat"
                id="grade_id"
                options={grades.map((item) => ({
                    value: String(item.id),
                    label: `Kelas ${item.name}`,
                }))}
                defaultValue={group === undefined ? undefined : String(group.gradeId)}
            />
            {school.hasMajors && (
                <SelectField
                    label="Jurusan"
                    id="major_id"
                    optionalLabel="Tanpa jurusan"
                    options={majors.map((item) => ({
                        value: String(item.id),
                        label: `${item.code} — ${item.name}`,
                    }))}
                    defaultValue={group?.majorId == null ? undefined : String(group.majorId)}
                />
            )}
            <SelectField
                label="Ruangan"
                id="room_id"
                optionalLabel="Belum ditentukan"
                options={rooms.map((item) => ({
                    value: String(item.id),
                    label: item.name,
                }))}
                defaultValue={group?.roomId == null ? undefined : String(group.roomId)}
            />
        </>
    );
}

/**
 * Kelas / Rombel of one academic year, filterable by grade and major.
 */
export default function ClassesIndex({
    school,
    classes,
    grades,
    majors,
    rooms,
    years,
}: {
    school: SchoolSummary;
    classes: ClassGroup[];
    grades: Grade[];
    majors: Major[];
    rooms: Room[];
    years: AcademicYear[];
}) {
    const [year, setYear] = useState(
        String(years.find((item) => item.status === 'active')?.id ?? years[0]?.id ?? ''),
    );
    const [grade, setGrade] = useState('');
    const [major, setMajor] = useState('');

    const rows = classes.filter(
        (item) =>
            String(item.yearId) === year &&
            (grade === '' || String(item.gradeId) === grade) &&
            (major === '' || item.major === major),
    );

    const gradeOptions = grades.map((item) => ({
        value: String(item.id),
        label: `Kelas ${item.name}`,
    }));

    return (
        <MasterPage
            school={school}
            title="Kelas"
            description="Rombongan belajar per tahun ajaran, lengkap dengan wali kelas dan ruangan."
            mock={false}
            actions={
                <FormDialog
                    route={store()}
                    title="Tambah kelas"
                    trigger={
                        <Button>
                            <PlusIcon />
                            Tambah kelas
                        </Button>
                    }
                >
                    <ClassForm
                        school={school}
                        years={years}
                        grades={grades}
                        majors={majors}
                        rooms={rooms}
                        defaultYear={year}
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
                    options={gradeOptions}
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
                    head={[
                        'Kelas',
                        school.homeroomLabel,
                        'Ruangan',
                        'Siswa',
                        '',
                    ]}
                >
                    {rows.map((item) => (
                        <TableRow key={item.id}>
                            <TableCell className="font-medium">
                                <Link
                                    href={show.url(item.id)}
                                    className="hover:underline"
                                >
                                    {item.name}
                                </Link>
                            </TableCell>
                            <TableCell>{item.homeroom ?? '—'}</TableCell>
                            <TableCell>{item.room ?? '—'}</TableCell>
                            <TableCell>{item.students}</TableCell>
                            <TableCell>
                                <div className="flex justify-end gap-2">
                                    <FormDialog
                                        route={update(item.id)}
                                        title={`Ubah ${item.name}`}
                                        trigger={
                                            <Button variant="ghost" size="sm">
                                                Ubah
                                            </Button>
                                        }
                                    >
                                        <ClassForm
                                            group={item}
                                            school={school}
                                            years={years}
                                            grades={grades}
                                            majors={majors}
                                            rooms={rooms}
                                            defaultYear={year}
                                        />
                                    </FormDialog>
                                    <ConfirmAction
                                        route={destroy(item.id)}
                                        title={`Hapus ${item.name}?`}
                                        description="Kelas akan dihapus dari tahun ajaran ini."
                                        confirmLabel="Hapus"
                                        trigger={
                                            <Button variant="ghost" size="sm">
                                                Hapus
                                            </Button>
                                        }
                                    />
                                </div>
                            </TableCell>
                        </TableRow>
                    ))}
                </DataTable>
            )}
        </MasterPage>
    );
}
