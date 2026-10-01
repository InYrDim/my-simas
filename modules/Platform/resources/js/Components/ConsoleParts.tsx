import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Pagination,
    PaginationContent,
    PaginationItem,
} from '@shared/components/ui/pagination';

export {
    DataTable,
    DefinitionList,
    EmptyState,
    OptionSelect,
    PageHeader,
    Panel,
    StatCard,
} from '@shared/components/page-parts';

import type { Paginated } from '../types/console';
import { consolePath } from './consolePath';

/*
 * Console-specific compositions. Every primitive (Badge, Card, Table,
 * Select, Pagination, Empty, Button) comes from modules/Shared; only the
 * domain wiring lives here.
 */

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

const statusBadge: Record<string, [BadgeVariant, string]> = {
    active: ['default', 'aktif'],
    suspended: ['destructive', 'ditangguhkan'],
    paid: ['default', 'lunas'],
    unpaid: ['outline', 'belum dibayar'],
    overdue: ['destructive', 'menunggak'],
    void: ['destructive', 'dibatalkan'],
    trial: ['secondary', 'uji coba'],
    trial_expired: ['destructive', 'uji coba berakhir'],
    due: ['outline', 'segera berakhir'],
    cancelled: ['secondary', 'berhenti'],
    pending: ['outline', 'pending'],
    inactive: ['secondary', 'nonaktif'],
    invited: ['outline', 'belum aktivasi'],
    unverified: ['outline', 'email belum terverifikasi'],
    registered: ['secondary', 'belum mengajukan'],
    approved: ['default', 'disetujui'],
    rejected: ['destructive', 'ditolak'],
    deactivated: ['destructive', 'nonaktif'],
};

/** Status word on a shared Badge; the word always carries the meaning. */
export function StatusChip({ status }: { status: string }) {
    const [variant, label] = statusBadge[status] ?? ['secondary', status];

    return <Badge variant={variant}>{label}</Badge>;
}

/** Single-series bar list: label, bar, value as text. */
export function BarList({
    rows,
}: {
    rows: { label: string; value: number; display: string }[];
}) {
    const max = Math.max(...rows.map((row) => row.value), 1);

    return (
        <ul className="flex flex-col gap-3">
            {rows.map((row) => (
                <li
                    key={row.label}
                    className="grid grid-cols-[2.5rem_1fr_6.5rem] items-center gap-3 text-xs"
                >
                    <span className="text-muted-foreground">{row.label}</span>
                    <span className="h-2 bg-muted">
                        <span
                            className="block h-2 bg-chart-1"
                            style={{ width: `${(row.value / max) * 100}%` }}
                        />
                    </span>
                    <span className="text-right text-foreground">
                        {row.display}
                    </span>
                </li>
            ))}
        </ul>
    );
}

/** Laravel paginator links as shared Pagination + Button, via Inertia. */
export function ListPagination({ page }: { page: Paginated<unknown> }) {
    if (page.last_page <= 1) {
        return null;
    }

    const labels: Record<string, string> = {
        '&laquo; Previous': 'Sebelumnya',
        'Next &raquo;': 'Berikutnya',
    };

    return (
        <div className="mt-4 flex flex-wrap items-center gap-3">
            <Pagination className="mx-0 w-auto justify-start">
                <PaginationContent>
                    {page.links.map((link, index) => {
                        const label = labels[link.label] ?? link.label;

                        return (
                            <PaginationItem key={`${label}-${index}`}>
                                <Button
                                    asChild={link.url !== null}
                                    variant={link.active ? 'outline' : 'ghost'}
                                    size="sm"
                                    disabled={link.url === null}
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
                                >
                                    {link.url !== null ? (
                                        <Link
                                            href={consolePath(link.url)}
                                            preserveScroll
                                        >
                                            {label}
                                        </Link>
                                    ) : (
                                        <span>{label}</span>
                                    )}
                                </Button>
                            </PaginationItem>
                        );
                    })}
                </PaginationContent>
            </Pagination>
            <span className="text-xs text-muted-foreground">
                {page.from}–{page.to} dari {page.total}
            </span>
        </div>
    );
}
