import { useState } from 'react';

import { DataTable, StatCard } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import PpdbPage from '../../Components/PpdbPage';
import { statusOf } from '../../Components/status';

interface Candidate {
    id: number;
    rank: number;
    name: string;
    path: string;
    score: number;
    decision: string;
}

interface SelectionProps {
    quota: { capacity: number; accepted: number; waitlist: number };
    candidates: Candidate[];
}

/**
 * Seleksi & Pengumuman: candidates ranked by score; the committee accepts
 * or moves each to the waitlist, then publishes the announcement.
 */
export default function Selection({ quota, candidates }: SelectionProps) {
    const [decisions, setDecisions] = useState<Record<number, string>>(
        Object.fromEntries(candidates.map((candidate) => [candidate.id, candidate.decision])),
    );
    const [published, setPublished] = useState(false);

    const accepted = Object.values(decisions).filter((value) => value === 'accepted').length;
    const waitlist = Object.values(decisions).filter((value) => value === 'waitlist').length;

    function decide(id: number, decision: string) {
        setPublished(false);
        setDecisions((current) => ({ ...current, [id]: decision }));
    }

    return (
        <PpdbPage
            title="Seleksi & Pengumuman"
            description="Urutan calon berdasarkan nilai seleksi."
            actions={
                <Button onClick={() => setPublished(true)} disabled={published}>
                    Umumkan hasil
                </Button>
            }
            width="max-w-6xl"
        >
            {published && (
                <p role="status" className="mb-6 border border-border bg-card p-3 text-sm text-muted-foreground shadow-sm">
                    Contoh saja — pengumuman belum dikirim ke pendaftar.
                </p>
            )}

            <div className="mb-8 grid grid-cols-3 gap-4">
                <StatCard label="Kuota" value={quota.capacity} />
                <StatCard label="Diterima" value={accepted} hint={`sisa ${quota.capacity - accepted} kursi`} />
                <StatCard label="Cadangan" value={waitlist} />
            </div>

            <DataTable head={['#', 'Nama', 'Jalur', 'Nilai', 'Keputusan', '']}>
                {candidates.map((candidate) => {
                    const decision = decisions[candidate.id];
                    const status = statusOf(decision);

                    return (
                        <TableRow key={candidate.id}>
                            <TableCell className="text-muted-foreground">{candidate.rank}</TableCell>
                            <TableCell className="font-medium">{candidate.name}</TableCell>
                            <TableCell>{candidate.path}</TableCell>
                            <TableCell>{candidate.score.toFixed(1)}</TableCell>
                            <TableCell>
                                <Badge variant={status.variant}>{status.label}</Badge>
                            </TableCell>
                            <TableCell>
                                <div className="flex justify-end gap-2">
                                    <Button
                                        size="sm"
                                        variant={decision === 'accepted' ? 'default' : 'outline'}
                                        aria-pressed={decision === 'accepted'}
                                        onClick={() => decide(candidate.id, 'accepted')}
                                    >
                                        Terima
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        aria-pressed={decision === 'waitlist'}
                                        onClick={() => decide(candidate.id, 'waitlist')}
                                    >
                                        Cadangan
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    );
                })}
            </DataTable>
        </PpdbPage>
    );
}
