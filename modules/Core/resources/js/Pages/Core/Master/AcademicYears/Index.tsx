import { PlusIcon } from 'lucide-react';

import { Panel } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';
import { DataTable } from '@shared/components/page-parts';

import { InputField, SelectField } from '../../../../Components/FormField';
import FormDialog from '../../../../Components/FormDialog';
import { formatDate } from '../../../../Components/format';
import MasterPage from '../../../../Components/MasterPage';
import StatusBadge from '../../../../Components/StatusBadge';
import type { AcademicYear, SchoolSummary } from '../../../../types/master';

function YearForm({ year }: { year?: AcademicYear }) {
    return (
        <>
            <InputField
                label="Nama tahun ajaran"
                id="name"
                placeholder="2026/2027"
                defaultValue={year?.name}
            />
            <SelectField
                label="Kurikulum"
                id="curriculum"
                options={['Kurikulum Merdeka', 'Kurikulum 2013']}
                defaultValue={year?.curriculum}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <InputField
                    label="Mulai"
                    id="start"
                    type="date"
                    defaultValue={year?.start}
                />
                <InputField
                    label="Selesai"
                    id="end"
                    type="date"
                    defaultValue={year?.end}
                />
            </div>
        </>
    );
}

/**
 * Tahun Ajaran & Semester: one year is active at a time; each year has a
 * Ganjil and a Genap semester with their own dates.
 */
export default function AcademicYearsIndex({
    school,
    years,
}: {
    school: SchoolSummary;
    years: AcademicYear[];
}) {
    return (
        <MasterPage
            school={school}
            title="Tahun Ajaran & Semester"
            description="Hanya satu tahun ajaran yang aktif. Data kelas dan penilaian mengikuti tahun ajaran."
            width="max-w-5xl"
            actions={
                <FormDialog
                    title="Tambah tahun ajaran"
                    trigger={
                        <Button>
                            <PlusIcon />
                            Tambah tahun ajaran
                        </Button>
                    }
                >
                    <YearForm />
                </FormDialog>
            }
        >
            <div className="flex flex-col gap-6">
                {years.map((year) => (
                    <Panel key={year.id}>
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div className="flex items-center gap-3">
                                    <h2 className="text-base font-semibold">
                                        {year.name}
                                    </h2>
                                    <StatusBadge status={year.status} />
                                </div>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {year.curriculum} · {formatDate(year.start)}{' '}
                                    – {formatDate(year.end)}
                                </p>
                            </div>

                            <div className="flex gap-3">
                                {year.status !== 'active' &&
                                    year.status !== 'archived' && (
                                        <Button variant="outline">
                                            Jadikan aktif
                                        </Button>
                                    )}
                                <FormDialog
                                    title={`Ubah ${year.name}`}
                                    trigger={
                                        <Button variant="outline">Ubah</Button>
                                    }
                                >
                                    <YearForm year={year} />
                                </FormDialog>
                            </div>
                        </div>

                        <div className="mt-6">
                            <DataTable head={['Semester', 'Mulai', 'Selesai']}>
                                {year.semesters.map((semester) => (
                                    <TableRow key={semester.name}>
                                        <TableCell>{semester.name}</TableCell>
                                        <TableCell>
                                            {formatDate(semester.start)}
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(semester.end)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </DataTable>
                        </div>
                    </Panel>
                ))}
            </div>
        </MasterPage>
    );
}
