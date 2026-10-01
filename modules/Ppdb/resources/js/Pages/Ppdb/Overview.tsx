import { Link } from '@inertiajs/react';

import { DataTable, Panel } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import PpdbPage from '../../Components/PpdbPage';
import { statusOf } from '../../Components/status';

interface OverviewProps {
    period: { name: string; status: string; closesOn: string };
    funnel: { key: string; label: string; count: number }[];
    waves: {
        id: number;
        name: string;
        opensOn: string;
        closesOn: string;
        status: string;
        applicants: number;
    }[];
}

/** Ringkasan PPDB: the admissions funnel and the registration waves. */
export default function Overview({ period, funnel, waves }: OverviewProps) {
    const top = funnel[0]?.count ?? 1;
    const periodStatus = statusOf(period.status);

    return (
        <PpdbPage
            title="Ringkasan PPDB"
            description={`${period.name} · ditutup ${period.closesOn}`}
            actions={
                <>
                    <Badge variant={periodStatus.variant} className="self-center">
                        {periodStatus.label}
                    </Badge>
                    <Button asChild>
                        <Link href="/ppdb/pendaftar">Lihat pendaftar</Link>
                    </Button>
                </>
            }
            width="max-w-6xl"
        >
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
                                    style={{ width: `${Math.round((step.count / top) * 100)}%` }}
                                />
                            </div>
                        </li>
                    ))}
                </ol>
            </Panel>

            <h2 className="mt-10 mb-3 text-sm font-semibold">Gelombang pendaftaran</h2>
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
        </PpdbPage>
    );
}
