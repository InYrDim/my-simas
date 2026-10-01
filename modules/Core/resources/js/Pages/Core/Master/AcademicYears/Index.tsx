import { PlusIcon } from 'lucide-react';

import {
    activate,
    destroy,
    store,
    update,
} from '@/actions/Modules/Core/App/Http/Controllers/AcademicYearController';

import { Panel } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';
import { DataTable } from '@shared/components/page-parts';

import ConfirmAction from '../../../../Components/ConfirmAction';
import { InputField, SelectField } from '../../../../Components/FormField';
import FormDialog from '../../../../Components/FormDialog';
import { formatDate } from '../../../../Components/format';
import MasterPage from '../../../../Components/MasterPage';
import StatusBadge from '../../../../Components/StatusBadge';
import type {
    AcademicYear,
    AcademicYearSuggestion,
    SchoolSummary,
} from '../../../../types/master';

function YearForm({
    year,
    suggestion,
}: {
    year?: AcademicYear;
    suggestion?: AcademicYearSuggestion;
}) {
    return (
        <>
            <InputField
                label="Nama tahun ajaran"
                id="name"
                placeholder="2026/2027"
                defaultValue={year?.name ?? suggestion?.name}
                hint={
                    suggestion !== undefined
                        ? 'Disarankan dari tahun ajaran terakhir.'
                        : undefined
                }
            />
            <SelectField
                label="Kurikulum"
                id="curriculum"
                options={['Kurikulum Merdeka', 'Kurikulum 2013']}
                defaultValue={year?.curriculum ?? suggestion?.curriculum}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <InputField
                    label="Mulai"
                    id="start_date"
                    type="date"
                    defaultValue={year?.start ?? suggestion?.start_date}
                />
                <InputField
                    label="Selesai"
                    id="end_date"
                    type="date"
                    defaultValue={year?.end ?? suggestion?.end_date}
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
    suggestion,
}: {
    school: SchoolSummary;
    years: AcademicYear[];
    suggestion: AcademicYearSuggestion;
}) {
    return (
        <MasterPage
            school={school}
            title="Tahun Ajaran & Semester"
            description="Hanya satu tahun ajaran yang aktif. Data kelas dan penilaian mengikuti tahun ajaran."
            width="max-w-5xl"
            mock={false}
            actions={
                <FormDialog
                    route={store()}
                    title="Tambah tahun ajaran"
                    trigger={
                        <Button>
                            <PlusIcon />
                            Tambah tahun ajaran
                        </Button>
                    }
                >
                    <YearForm suggestion={suggestion} />
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
                                {year.status === 'draft' && (
                                    <ConfirmAction
                                        route={activate(year.id)}
                                        title={`Aktifkan ${year.name}?`}
                                        description="Tahun ajaran yang sedang aktif akan diarsipkan."
                                        confirmLabel="Jadikan aktif"
                                        trigger={
                                            <Button variant="outline">
                                                Jadikan aktif
                                            </Button>
                                        }
                                    />
                                )}
                                {year.status === 'draft' && (
                                    <ConfirmAction
                                        route={destroy(year.id)}
                                        title={`Hapus ${year.name}?`}
                                        description="Tahun ajaran beserta semesternya akan dihapus."
                                        confirmLabel="Hapus"
                                        trigger={
                                            <Button variant="outline">
                                                Hapus
                                            </Button>
                                        }
                                    />
                                )}
                                <FormDialog
                                    route={update(year.id)}
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
