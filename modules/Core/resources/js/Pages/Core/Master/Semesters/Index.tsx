import { CalendarDaysIcon } from 'lucide-react';

import { DataTable, Panel } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import FormDialog from '../../../../Components/FormDialog';
import { InputField } from '../../../../Components/FormField';
import { formatDate } from '../../../../Components/format';
import MasterPage from '../../../../Components/MasterPage';
import StatusBadge from '../../../../Components/StatusBadge';
import type { SchoolSummary, SemesterRow } from '../../../../types/master';

function SemesterForm({ semester }: { semester: SemesterRow }) {
    return (
        <>
            <p className="text-sm text-muted-foreground">
                {semester.year} · Semester {semester.name}
            </p>
            <div className="grid gap-4 sm:grid-cols-2">
                <InputField label="Mulai" id="start" type="date" defaultValue={semester.start} />
                <InputField label="Selesai" id="end" type="date" defaultValue={semester.end} />
            </div>
        </>
    );
}

/**
 * Semester: every Ganjil and Genap across the academic years. One semester
 * runs at a time; it sets the period for grades, attendance and reports.
 */
export default function SemestersIndex({
    school,
    semesters,
}: {
    school: SchoolSummary;
    semesters: SemesterRow[];
}) {
    const current = semesters.find((semester) => semester.status === 'current');

    return (
        <MasterPage
            school={school}
            title="Semester"
            description="Hanya satu semester yang berjalan. Penilaian, absensi, dan laporan mengikuti semester ini."
            width="max-w-5xl"
        >
            {current !== undefined && current.week !== null ? (
                <Panel className="mb-8">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-3">
                                <h2 className="text-base font-semibold">
                                    Semester {current.name} {current.year}
                                </h2>
                                <StatusBadge status="current" />
                            </div>
                            <p className="mt-1 flex items-center gap-2 text-sm text-muted-foreground">
                                <CalendarDaysIcon className="size-4" aria-hidden />
                                {formatDate(current.start)} – {formatDate(current.end)}
                            </p>
                        </div>
                        <FormDialog
                            title={`Ubah semester ${current.name} ${current.year}`}
                            trigger={<Button variant="outline">Ubah tanggal</Button>}
                        >
                            <SemesterForm semester={current} />
                        </FormDialog>
                    </div>

                    <div className="mt-6">
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">Minggu ke-{current.week}</span>
                            <span className="font-medium">dari {current.weeks} minggu</span>
                        </div>
                        <div
                            role="progressbar"
                            aria-label="Kemajuan semester"
                            aria-valuenow={current.week}
                            aria-valuemin={0}
                            aria-valuemax={current.weeks}
                            className="mt-2 h-2 bg-muted"
                        >
                            <div
                                className="h-full bg-primary"
                                style={{ width: `${Math.round((current.week / current.weeks) * 100)}%` }}
                            />
                        </div>
                    </div>
                </Panel>
            ) : (
                <Panel className="mb-8">
                    <p className="text-sm text-muted-foreground">
                        Belum ada semester yang berjalan. Pilih satu dari daftar di bawah.
                    </p>
                </Panel>
            )}

            <DataTable head={['Tahun ajaran', 'Semester', 'Periode', 'Minggu', 'Status', '']}>
                {semesters.map((semester) => (
                    <TableRow key={semester.id}>
                        <TableCell className="font-medium">{semester.year}</TableCell>
                        <TableCell>{semester.name}</TableCell>
                        <TableCell>
                            {formatDate(semester.start)} – {formatDate(semester.end)}
                        </TableCell>
                        <TableCell>{semester.weeks}</TableCell>
                        <TableCell>
                            <StatusBadge status={semester.status} />
                        </TableCell>
                        <TableCell>
                            <div className="flex justify-end gap-2">
                                {semester.status === 'upcoming' && (
                                    <Button size="sm" variant="outline">
                                        Jadikan berjalan
                                    </Button>
                                )}
                                <FormDialog
                                    title={`Ubah semester ${semester.name} ${semester.year}`}
                                    trigger={
                                        <Button size="sm" variant="outline">
                                            Ubah
                                        </Button>
                                    }
                                >
                                    <SemesterForm semester={semester} />
                                </FormDialog>
                            </div>
                        </TableCell>
                    </TableRow>
                ))}
            </DataTable>
        </MasterPage>
    );
}
