import type { ReactNode } from 'react';

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

/*
 * Page compositions shared by every module's list/detail pages (console,
 * school-side master data). Pure presentation over the shadcn primitives —
 * no domain wiring.
 */

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
