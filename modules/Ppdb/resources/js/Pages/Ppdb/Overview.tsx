import { Link } from '@inertiajs/react';

import { applicants, settings } from '@/routes/ppdb';
import { DataTable, EmptyState, Panel } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import PpdbPage from '../../Components/PpdbPage';
import { statusOf } from '../../Components/status';

interface OverviewProps {
    period: { id: number; name: string; status: string; closesOn: string | null } | null;
    funnel: { key: string; label: string; count: number }[];
    waves: {
        id: number;
        name: string;
        opensOn: string;
        closesOn: string;
        status: string;
        applicants: number;
    }[];
    can: { manageSettings: boolean };
}

/** Ringkasan PPDB: the admissions funnel and the registration waves. */
export default function Overview({ period, funnel, waves, can }: OverviewProps) {
    if (period === null) {
        return (
            <PpdbPage title="Ringkasan PPDB" description="Belum ada periode PPDB yang berjalan." width="max-w-6xl">
                <EmptyState>
                    Belum ada periode PPDB yang berjalan.
                    {can.manageSettings && (
                        <>
                            {' '}
                            <Link href={settings.url()} className="underline underline-offset-4">
                                Atur periode, gelombang, dan kuota
                            </Link>{' '}
                            untuk memulai.
                        </>
                    )}
                </EmptyState>
            </PpdbPage>
        );
    }

    const top = funnel[0]?.count ?? 1;
    const periodStatus = statusOf(period.status);

    return (
        <PpdbPage
            title="Ringkasan PPDB"
            description={period.closesOn === null ? period.name : `${period.name} · ditutup ${period.closesOn}`}
            actions={
                <>
                    <Badge variant={periodStatus.variant} className="self-center">
                        {periodStatus.label}
                    </Badge>
                    <Button asChild>
                        <Link href={applicants.url()}>Lihat pendaftar</Link>
                    </Button>
                </>
            }
            width="max-w-6xl"
        >
            {funnel.length > 0 && (
                <Panel title="Alur penerimaan">
                    <ol className="flex flex-col gap-4">
                        {funnel.map((step) => (
                            <li key={step.key}>
                                <div className="flex items-baseline justify-between text-sm">
                                    <span>{step.label}</span>
                                    <span className="font-semibold">{step.count}</span>
                                </div>
                                <div
                                    role="progressbar"
                                    aria-label={step.label}
                                    aria-valuenow={step.count}
                                    aria-valuemin={0}
                                    aria-valuemax={top}
                                    className="mt-1.5 h-2 bg-muted"
                                >
                                    <div
                                        className="h-full bg-primary"
                                        style={{ width: `${Math.round((step.count / Math.max(top, 1)) * 100)}%` }}
                                    />
                                </div>
                            </li>
                        ))}
                    </ol>
                </Panel>
            )}

            <h2 className={`mb-3 text-sm font-semibold ${funnel.length > 0 ? 'mt-10' : ''}`}>Gelombang pendaftaran</h2>
            {waves.length === 0 ? (
                <EmptyState>
                    Periode ini belum punya gelombang pendaftaran.
                    {can.manageSettings && (
                        <>
                            {' '}
                            <Link href={settings.url()} className="underline underline-offset-4">
                                Tambah gelombang
                            </Link>
                            .
                        </>
                    )}
                </EmptyState>
            ) : (
                <DataTable head={['Gelombang', 'Dibuka', 'Ditutup', 'Pendaftar', 'Status']}>
                    {waves.map((wave) => {
                        const status = statusOf(wave.status);

                        return (
                            <TableRow key={wave.id}>
                                <TableCell className="font-medium">{wave.name}</TableCell>
                                <TableCell>{wave.opensOn}</TableCell>
                                <TableCell>{wave.closesOn}</TableCell>
                                <TableCell>{wave.applicants}</TableCell>
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
