import { Head } from '@inertiajs/react';
import { PrinterIcon } from 'lucide-react';

import { EmptyState } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@shared/components/ui/table';

import type { SchoolSummary } from '../../../types/master';

interface ReportPrintProps {
    school: SchoolSummary;
    title: string;
    /** Name of the academic year the report is for. */
    period: string;
    printedOn: string;
    columns: string[];
    rows: (string | number | null)[][];
}

/**
 * A report laid out for paper: no application shell, only the school, the
 * report and its table. "Cetak" opens the browser's print dialog, which
 * also saves it as a PDF.
 */
export default function ReportPrint({ school, title, period, printedOn, columns, rows }: ReportPrintProps) {
    return (
        <div className="mx-auto min-h-screen max-w-5xl bg-background p-6 text-foreground print:max-w-none print:p-0">
            <Head title={`${title} — ${period}`} />

            <header className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm text-muted-foreground">{school.name}</p>
                    <h1 className="mt-1 text-xl font-semibold">{title}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Tahun ajaran {period} · Dicetak {printedOn} · {rows.length} baris
                    </p>
                </div>
                <Button className="print:hidden" onClick={() => window.print()}>
                    <PrinterIcon />
                    Cetak
                </Button>
            </header>

            <div className="mt-6">
                {rows.length === 0 ? (
                    <EmptyState>Tidak ada data untuk tahun ajaran ini.</EmptyState>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {columns.map((column) => (
                                    <TableHead key={column}>{column}</TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((row, index) => (
                                <TableRow key={index}>
                                    {row.map((cell, cellIndex) => (
                                        <TableCell key={cellIndex}>{cell ?? ''}</TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </div>
    );
}
