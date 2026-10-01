import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
} from '@shared/components/ui/empty';
import {
    Pagination,
    PaginationContent,
    PaginationItem,
} from '@shared/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@shared/components/ui/select';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@shared/components/ui/table';
import { cn } from '@shared/lib/utils';

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
    deactivated: ['destructive', 'nonaktif'],
};

/** Status word on a shared Badge; the word always carries the meaning. */
export function StatusChip({ status }: { status: string }) {
    const [variant, label] = statusBadge[status] ?? ['secondary', status];

    return <Badge variant={variant}>{label}</Badge>;
}

export function PageHeader({
    title,
    description,
    actions,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 className="text-xl font-semibold text-foreground">
                    {title}
                </h1>
                {description !== undefined && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions !== undefined && (
                <div className="flex flex-wrap gap-3">{actions}</div>
            )}
        </div>
    );
}

/** A titled card section. */
export function Panel({
    title,
    children,
    className,
}: {
    title?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <Card className={className}>
            {title !== undefined && (
                <CardHeader>
                    <CardTitle>{title}</CardTitle>
                </CardHeader>
            )}
            <CardContent>{children}</CardContent>
        </Card>
    );
}

export function StatCard({
    label,
    value,
    hint,
}: {
    label: string;
    value: string | number;
    hint?: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-xl">{value}</CardTitle>
                {hint !== undefined && (
                    <CardDescription>{hint}</CardDescription>
                )}
            </CardHeader>
        </Card>
    );
}

export function DefinitionList({ rows }: { rows: [string, ReactNode][] }) {
    return (
        <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
            {rows.map(([label, value]) => (
                <div key={label} className="contents">
                    <dt className="text-muted-foreground">{label}</dt>
                    <dd className="min-w-0 break-words text-foreground">
                        {value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export function EmptyState({ children }: { children: ReactNode }) {
    return (
        <Empty className="bg-card shadow-sm">
            <EmptyHeader>
                <EmptyDescription>{children}</EmptyDescription>
            </EmptyHeader>
        </Empty>
    );
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

/** Header row from a label list, body rows from children (TableRow). */
export function DataTable({
    head,
    children,
}: {
    head: string[];
    children: ReactNode;
}) {
    return (
        <div className="bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow>
                        {head.map((label, index) => (
                            <TableHead key={`${label}-${index}`}>
                                {label}
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>{children}</TableBody>
            </Table>
        </div>
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

const ALL = '__all__';

/**
 * Shared Select with plain string values. `allLabel` adds an "all" option
 * that maps to the empty string (Radix items cannot have an empty value).
 */
export function OptionSelect({
    value,
    onChange,
    options,
    label,
    allLabel,
    placeholder,
    className,
}: {
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string }[];
    label: string;
    allLabel?: string;
    placeholder?: string;
    className?: string;
}) {
    return (
        <Select
            value={value === '' && allLabel !== undefined ? ALL : value}
            onValueChange={(next) => onChange(next === ALL ? '' : next)}
        >
            <SelectTrigger aria-label={label} className={cn('w-full', className)}>
                <SelectValue placeholder={placeholder ?? allLabel} />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    {allLabel !== undefined && (
                        <SelectItem value={ALL}>{allLabel}</SelectItem>
                    )}
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectGroup>
            </SelectContent>
        </Select>
    );
}
