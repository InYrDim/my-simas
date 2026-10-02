import { Link, router, useForm, usePage } from '@inertiajs/react';

import { publish, update } from '@/actions/Modules/Ppdb/App/Http/Controllers/SelectionController';
import { selection, settings } from '@/routes/ppdb';
import { DataTable, EmptyState, OptionSelect, StatCard } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';

import ConfirmAction from '../../Components/ConfirmAction';
import PpdbPage from '../../Components/PpdbPage';
import { statusOf } from '../../Components/status';

interface PathSummary {
    id: number;
    name: string;
    quota: number;
    verified: number;
    accepted: number;
    waitlist: number;
    pending: number;
}

interface Candidate {
    id: number;
    rank: number | null;
    number: string;
    name: string;
    score: string | null;
    decision: string;
    enrolled: boolean;
}

interface SelectionProps {
    period: { id: number; name: string; status: string; resultsPublished: boolean; publishedOn: string | null } | null;
    paths: PathSummary[];
    selectedPathId: number | null;
    quota: { capacity: number; accepted: number; waitlist: number };
    candidates: Candidate[];
    pending: number;
    can: { manage: boolean };
}

type Row = { applicant_id: number; score: string; decision: string };

/** The scores and decisions of one path: edited here, saved together. */
function Ranking({
    periodPublished,
    pathId,
    candidates,
    canManage,
}: {
    periodPublished: boolean;
    pathId: number;
    candidates: Candidate[];
    canManage: boolean;
}) {
    const form = useForm<{ path_id: number; rows: Row[] }>({
        path_id: pathId,
        rows: candidates.map((candidate) => ({
            applicant_id: candidate.id,
            score: candidate.score ?? '',
            decision: candidate.decision,
        })),
    });
    const errors: Partial<Record<string, string>> = form.errors;

    function change(index: number, patch: Partial<Row>) {
        form.setData(
            'rows',
            form.data.rows.map((row, position) => (position === index ? { ...row, ...patch } : row)),
        );
    }

    if (candidates.length === 0) {
        return <EmptyState>Belum ada pendaftar terverifikasi di jalur ini. Pendaftar muncul di sini setelah panitia memverifikasinya.</EmptyState>;
    }

    return (
        <form
            className="flex flex-col gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(update.url(), { preserveScroll: true, onSuccess: () => form.setDefaults() });
            }}
        >
            {errors.quota !== undefined && (
                <Alert variant="destructive">
                    <AlertDescription>{errors.quota}</AlertDescription>
                </Alert>
            )}

            <DataTable head={['#', 'No. daftar', 'Nama', 'Nilai', 'Keputusan', '']}>
                {candidates.map((candidate, index) => {
                    const row = form.data.rows[index];
                    const status = statusOf(row.decision);
                    const locked = !canManage || candidate.enrolled;
                    const scoreError = errors[`rows.${index}.score`];
                    const decisionError = errors[`rows.${index}.decision`] ?? errors[`rows.${index}.applicant_id`];
                    // After the announcement a waiting-list applicant can still be moved up.
                    const canPromote = periodPublished && candidate.decision === 'waitlist';
                    const decisionsLocked = locked || (periodPublished && !canPromote);

                    return (
                        <TableRow key={candidate.id}>
                            <TableCell className="text-muted-foreground">{candidate.rank ?? '—'}</TableCell>
                            <TableCell className="font-mono text-xs">{candidate.number}</TableCell>
                            <TableCell className="font-medium">{candidate.name}</TableCell>
                            <TableCell className="w-28">
                                <Input
                                    type="number"
                                    inputMode="decimal"
                                    min={0}
                                    max={100}
                                    step="0.01"
                                    aria-label={`Nilai ${candidate.name}`}
                                    value={row.score}
                                    disabled={locked || periodPublished}
                                    onChange={(event) => change(index, { score: event.target.value })}
                                    aria-invalid={scoreError !== undefined}
                                />
                                {scoreError !== undefined && <p className="mt-1 text-xs text-destructive">{scoreError}</p>}
                            </TableCell>
                            <TableCell>
                                <Badge variant={status.variant}>{status.label}</Badge>
                                {decisionError !== undefined && <p className="mt-1 text-xs text-destructive">{decisionError}</p>}
                            </TableCell>
                            <TableCell>
                                <div className="flex justify-end gap-2">
                                    {(
                                        [
                                            ['accepted', 'Terima'],
                                            ['waitlist', 'Cadangan'],
                                            ['rejected', 'Tolak'],
                                        ] as const
                                    ).map(([decision, label]) => (
                                        <Button
                                            key={decision}
                                            type="button"
                                            size="sm"
                                            variant={row.decision === decision ? 'default' : 'outline'}
                                            aria-pressed={row.decision === decision}
                                            disabled={decisionsLocked || (periodPublished && decision !== 'accepted')}
                                            onClick={() => change(index, { decision: row.decision === decision ? 'pending' : decision })}
                                        >
                                            {label}
                                        </Button>
                                    ))}
                                </div>
                            </TableCell>
                        </TableRow>
                    );
                })}
            </DataTable>

            {canManage && (
                <div>
                    <Button type="submit" disabled={form.processing || !form.isDirty}>
                        Simpan seleksi
                    </Button>
                </div>
            )}
        </form>
    );
}

/**
 * Seleksi & Pengumuman: candidates of a path ranked by score; the
 * committee decides within the quota, then announces the results.
 */
export default function Selection({ period, paths, selectedPathId, quota, candidates, pending, can }: SelectionProps) {
    const errors = usePage<{ errors: Record<string, string> }>().props.errors;

    if (period === null) {
        return (
            <PpdbPage title="Seleksi & Pengumuman" description="Belum ada periode PPDB." width="max-w-6xl">
                <EmptyState>
                    Belum ada periode PPDB, jadi belum ada yang diseleksi.{' '}
                    <Link href={settings.url()} className="underline underline-offset-4">
                        Pengaturan PPDB
                    </Link>
                </EmptyState>
            </PpdbPage>
        );
    }

    return (
        <PpdbPage
            title="Seleksi & Pengumuman"
            description={`Urutan pendaftar terverifikasi berdasarkan nilai seleksi · ${period.name}`}
            actions={
                period.resultsPublished ? (
                    <Badge className="self-center">Diumumkan {period.publishedOn}</Badge>
                ) : can.manage ? (
                    <ConfirmAction
                        trigger={<Button>Umumkan hasil</Button>}
                        title="Umumkan hasil seleksi?"
                        description="Setelah diumumkan, tiap pendaftar melihat keputusannya di halaman akun mereka. Nilai tidak lagi bisa diubah; yang masih bisa dilakukan hanya menaikkan pendaftar dari cadangan menjadi diterima."
                        confirmLabel="Umumkan hasil"
                        onConfirm={() => router.post(publish.url(), { period_id: period.id }, { preserveScroll: true })}
                    />
                ) : undefined
            }
            width="max-w-6xl"
        >
            {errors.publish !== undefined && (
                <Alert variant="destructive" className="mb-6">
                    <AlertDescription>{errors.publish}</AlertDescription>
                </Alert>
            )}

            {paths.length === 0 ? (
                <EmptyState>Periode ini belum punya jalur.</EmptyState>
            ) : (
                <>
                    <div className="mb-6 grid gap-4 sm:grid-cols-[minmax(0,18rem)_1fr] sm:items-end">
                        <OptionSelect
                            label="Jalur"
                            value={String(selectedPathId ?? '')}
                            onChange={(value) => router.get(selection.url({ query: { jalur: value, periode: period.id } }), {}, { preserveScroll: true })}
                            options={paths.map((path) => ({
                                value: String(path.id),
                                label: `${path.name} · ${path.accepted}/${path.quota} diterima`,
                            }))}
                        />
                        {pending > 0 && !period.resultsPublished && (
                            <p className="text-sm text-muted-foreground">{pending} pendaftar terverifikasi di semua jalur belum diputuskan.</p>
                        )}
                    </div>

                    <div className="mb-8 grid grid-cols-3 gap-4">
                        <StatCard label="Kuota" value={quota.capacity} />
                        <StatCard label="Diterima" value={quota.accepted} hint={`sisa ${Math.max(quota.capacity - quota.accepted, 0)} kursi`} />
                        <StatCard label="Cadangan" value={quota.waitlist} />
                    </div>

                    {selectedPathId !== null && (
                        <Ranking
                            key={`${selectedPathId}-${candidates.map((candidate) => `${candidate.id}:${candidate.score}:${candidate.decision}`).join('|')}`}
                            periodPublished={period.resultsPublished}
                            pathId={selectedPathId}
                            candidates={candidates}
                            canManage={can.manage}
                        />
                    )}
                </>
            )}
        </PpdbPage>
    );
}
