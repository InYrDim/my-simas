import { DownloadIcon, PrinterIcon } from 'lucide-react';
import { useState } from 'react';

import {
    download,
    printView,
} from '@/actions/Modules/Core/App/Http/Controllers/ReportController';
import { EmptyState, OptionSelect, Panel } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';

import MasterPage from '../../../Components/MasterPage';
import type { SchoolSummary } from '../../../types/master';

interface ReportsProps {
    school: SchoolSummary;
    groups: {
        title: string;
        reports: {
            key: string;
            name: string;
            description: string;
            /** False for a report that is announced but not built yet. */
            available: boolean;
        }[];
    }[];
    years: { value: string; label: string }[];
    /** The academic year selected at first: the active one. Empty when the school has none. */
    yearId: string;
}

/** Laporan: pick an academic year, then download a report as CSV or open it for printing. */
export default function Reports({ school, groups, years, yearId }: ReportsProps) {
    const [year, setYear] = useState(yearId);

    return (
        <MasterPage
            school={school}
            title="Laporan"
            description="Unduh laporan sekolah untuk dicetak atau diolah lebih lanjut."
            width="max-w-5xl"
            mock={false}
        >
            {years.length === 0 ? (
                <div className="mb-6">
                    <EmptyState>
                        Belum ada tahun ajaran. Tambahkan tahun ajaran di Master Data agar laporan bisa dibuat.
                    </EmptyState>
                </div>
            ) : (
                <div className="mb-6 max-w-xs">
                    <OptionSelect label="Tahun ajaran" value={year} onChange={setYear} options={years} />
                </div>
            )}

            <div className="flex flex-col gap-8">
                {groups.map((group, index) => (
                    <section key={group.title} aria-labelledby={`laporan-grup-${index}`}>
                        <h2 id={`laporan-grup-${index}`} className="mb-3 text-sm font-semibold">
                            {group.title}
                        </h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            {group.reports.map((report) => (
                                <Panel key={report.key}>
                                    <p className="font-medium">{report.name}</p>
                                    <p className="mt-1 text-sm text-muted-foreground">{report.description}</p>
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {!report.available ? (
                                            <Badge variant="secondary">Segera hadir</Badge>
                                        ) : (
                                            year !== '' && (
                                                <>
                                                    <Button asChild size="sm" variant="outline">
                                                        <a
                                                            href={download.url(report.key, { query: { tahun: year } })}
                                                            aria-label={`Unduh CSV ${report.name}`}
                                                        >
                                                            <DownloadIcon />
                                                            CSV
                                                        </a>
                                                    </Button>
                                                    <Button asChild size="sm" variant="outline">
                                                        <a
                                                            href={printView.url(report.key, { query: { tahun: year } })}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            aria-label={`Cetak ${report.name}`}
                                                        >
                                                            <PrinterIcon />
                                                            Cetak
                                                        </a>
                                                    </Button>
                                                </>
                                            )
                                        )}
                                    </div>
                                </Panel>
                            ))}
                        </div>
                    </section>
                ))}
            </div>
        </MasterPage>
    );
}
