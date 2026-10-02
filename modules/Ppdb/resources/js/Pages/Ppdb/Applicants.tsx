import { Link } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

import { index } from '@/actions/Modules/Ppdb/App/Http/Controllers/ApplicantController';
import { settings } from '@/routes/ppdb';
import { create, show } from '@/routes/ppdb/applicants';
import { DataTable, EmptyState, OptionSelect } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';
import ListPager, { type Pagination, useListFilters } from '@shared/components/ListPager';

import PpdbPage from '../../Components/PpdbPage';
import { statusOf } from '../../Components/status';

interface Applicant {
    id: number;
    number: string;
    name: string;
    origin: string;
    pathName: string | null;
    status: string;
    registeredOn: string;
    source: string;
}

interface ApplicantsProps {
    period: { id: number; name: string } | null;
    applicants: Applicant[];
    pagination: Pagination;
    filters: { cari: string; jalur: string; status: string };
    paths: { value: string; label: string }[];
    statuses: { value: string; label: string }[];
    can: { manage: boolean };
}

/** Pendaftar: every applicant of the period, searchable and filterable. */
export default function Applicants({ period, applicants, pagination, filters: initial, paths, statuses, can }: ApplicantsProps) {
    const url = index.url();
    const { filters, set } = useListFilters(url, initial);
    const filtered = initial.cari !== '' || initial.jalur !== '' || initial.status !== '';

    if (period === null) {
        return (
            <PpdbPage title="Pendaftar" description="Belum ada periode PPDB." width="max-w-6xl">
                <EmptyState>
                    Belum ada periode PPDB, jadi belum ada pendaftar.
                    {can.manage && (
                        <>
                            {' '}
                            Admin sekolah mengatur periode di{' '}
                            <Link href={settings.url()} className="underline underline-offset-4">
                                Pengaturan PPDB
                            </Link>
                            .
                        </>
                    )}
                </EmptyState>
            </PpdbPage>
        );
    }

    return (
        <PpdbPage
            title="Pendaftar"
            description={`${pagination.total} pendaftar · ${period.name}`}
            actions={
                can.manage ? (
                    <Button asChild>
                        <Link href={create.url()}>
                            <PlusIcon />
                            Tambah pendaftar
                        </Link>
                    </Button>
                ) : undefined
            }
            width="max-w-6xl"
        >
            <div className="mb-6 grid gap-3 sm:grid-cols-3">
                <Input
                    type="search"
                    aria-label="Cari pendaftar"
                    placeholder="Cari nama atau nomor pendaftaran"
                    value={filters.cari}
                    onChange={(event) => set('cari', event.target.value)}
                />
                <OptionSelect label="Jalur" value={filters.jalur} onChange={(value) => set('jalur', value)} options={paths} allLabel="Semua jalur" />
                <OptionSelect label="Status" value={filters.status} onChange={(value) => set('status', value)} options={statuses} allLabel="Semua status" />
            </div>

            {applicants.length === 0 ? (
                <EmptyState>{filtered ? 'Tidak ada pendaftar yang cocok.' : 'Belum ada pendaftar.'}</EmptyState>
            ) : (
                <DataTable head={['No. daftar', 'Nama', 'Asal sekolah', 'Jalur', 'Tanggal', 'Status']}>
                    {applicants.map((applicant) => {
                        const status = statusOf(applicant.status);

                        return (
                            <TableRow key={applicant.id}>
                                <TableCell className="font-mono text-xs">{applicant.number}</TableCell>
                                <TableCell className="font-medium">
                                    <Link href={show.url({ applicant: applicant.id })} className="hover:underline">
                                        {applicant.name}
                                    </Link>
                                </TableCell>
                                <TableCell className="text-muted-foreground">{applicant.origin}</TableCell>
                                <TableCell>{applicant.pathName ?? '—'}</TableCell>
                                <TableCell>{applicant.registeredOn}</TableCell>
                                <TableCell>
                                    <Badge variant={status.variant}>{status.label}</Badge>
                                </TableCell>
                            </TableRow>
                        );
                    })}
                </DataTable>
            )}

            <ListPager url={url} filters={initial} pagination={pagination} />
        </PpdbPage>
    );
}
