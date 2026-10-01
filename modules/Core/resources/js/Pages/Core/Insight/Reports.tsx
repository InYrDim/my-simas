import { DownloadIcon } from 'lucide-react';
import { useState } from 'react';

import { DataTable, OptionSelect, Panel } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import MasterPage from '../../../Components/MasterPage';
import type { SchoolSummary } from '../../../types/master';

interface ReportsProps {
    school: SchoolSummary;
    groups: {
        title: string;
        reports: { key: string; name: string; description: string; formats: string[] }[];
    }[];
    recent: {
        name: string;
        period: string;
        createdBy: string;
        createdOn: string;
        status: 'ready' | 'failed';
    }[];
}

const periods = [
    { value: 'sep-2026', label: 'September 2026' },
    { value: 'ganjil-2026', label: 'Semester Ganjil 2026/2027' },
    { value: 'genap-2025', label: 'Semester Genap 2025/2026' },
];

/** Laporan: pick a period, download a report as PDF or Excel, see what was made recently. */
export default function Reports({ school, groups, recent }: ReportsProps) {
    const [period, setPeriod] = useState(periods[0].value);
    const [notice, setNotice] = useState<string | null>(null);

    return (
        <MasterPage
            school={school}
            title="Laporan"
            description="Unduh laporan sekolah untuk dicetak atau diolah lebih lanjut."
            width="max-w-5xl"
        >
            <div className="mb-6 max-w-xs">
                <OptionSelect label="Periode" value={period} onChange={setPeriod} options={periods} />
            </div>

            {notice !== null && (
                <p role="status" className="mb-6 border border-border bg-card p-3 text-sm text-muted-foreground shadow-sm">
                    {notice}
                </p>
            )}

            <div className="flex flex-col gap-8">
                {groups.map((group) => (
                    <section key={group.title} aria-labelledby={`laporan-${group.title}`}>
                        <h2 id={`laporan-${group.title}`} className="mb-3 text-sm font-semibold">
                            {group.title}
                        </h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            {group.reports.map((report) => (
                                <Panel key={report.key}>
                                    <p className="font-medium">{report.name}</p>
                                    <p className="mt-1 text-sm text-muted-foreground">{report.description}</p>
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {report.formats.map((format) => (
                                            <Button
                                                key={format}
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setNotice(`Contoh saja — ${report.name} (${format}) belum dibuat.`)
                                                }
                                            >
                                                <DownloadIcon />
                                                {format}
                                            </Button>
                                        ))}
                                    </div>
                                </Panel>
                            ))}
                        </div>
                    </section>
                ))}

                <section aria-labelledby="laporan-terakhir">
                    <h2 id="laporan-terakhir" className="mb-3 text-sm font-semibold">
                        Terakhir dibuat
                    </h2>
                    <DataTable head={['Laporan', 'Periode', 'Dibuat oleh', 'Tanggal', 'Status']}>
                        {recent.map((row) => (
                            <TableRow key={`${row.name}-${row.createdOn}`}>
                                <TableCell className="font-medium">{row.name}</TableCell>
                                <TableCell>{row.period}</TableCell>
                                <TableCell className="text-muted-foreground">{row.createdBy}</TableCell>
                                <TableCell>{row.createdOn}</TableCell>
                                <TableCell>
                                    {row.status === 'ready' ? (
                                        <Badge>Siap diunduh</Badge>
                                    ) : (
                                        <Badge variant="destructive">Gagal dibuat</Badge>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </DataTable>
                </section>
            </div>
        </MasterPage>
    );
}
