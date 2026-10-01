import { useState } from 'react';

import { DataTable, EmptyState, OptionSelect } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';

import PpdbPage from '../../Components/PpdbPage';
import { statusOf } from '../../Components/status';

interface Applicant {
    id: number;
    number: string;
    name: string;
    origin: string;
    path: string;
    pathLabel: string;
    status: string;
    submittedOn: string;
}

interface ApplicantsProps {
    applicants: Applicant[];
    paths: { value: string; label: string }[];
}

/** Pendaftar: every applicant, searchable and filterable by admission path. */
export default function Applicants({ applicants, paths }: ApplicantsProps) {
    const [query, setQuery] = useState('');
    const [path, setPath] = useState('');

    const rows = applicants.filter(
        (applicant) =>
            (path === '' || applicant.path === path) &&
            applicant.name.toLowerCase().includes(query.trim().toLowerCase()),
    );

    return (
        <PpdbPage
            title="Pendaftar"
            description={`${applicants.length} pendaftar pada gelombang berjalan.`}
            actions={<Button variant="outline">Unduh daftar</Button>}
            width="max-w-6xl"
        >
            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <Input
                    type="search"
                    aria-label="Cari nama pendaftar"
                    placeholder="Cari nama pendaftar"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    className="sm:max-w-xs"
                />
                <div className="sm:w-48">
                    <OptionSelect
                        label="Jalur"
                        value={path}
                        onChange={setPath}
                        options={paths}
                        allLabel="Semua jalur"
                    />
                </div>
            </div>

            {rows.length === 0 ? (
                <EmptyState>Tidak ada pendaftar yang cocok.</EmptyState>
            ) : (
                <DataTable head={['No. daftar', 'Nama', 'Asal sekolah', 'Jalur', 'Tanggal', 'Status']}>
                    {rows.map((applicant) => {
                        const status = statusOf(applicant.status);

                        return (
                            <TableRow key={applicant.id}>
                                <TableCell className="font-mono text-xs">{applicant.number}</TableCell>
                                <TableCell className="font-medium">{applicant.name}</TableCell>
                                <TableCell className="text-muted-foreground">{applicant.origin}</TableCell>
                                <TableCell>{applicant.pathLabel}</TableCell>
                                <TableCell>{applicant.submittedOn}</TableCell>
                                <TableCell>
                                    <Badge variant={status.variant}>{status.label}</Badge>
                                </TableCell>
                            </TableRow>
                        );
                    })}
                </DataTable>
            )}
        </PpdbPage>
    );
}
