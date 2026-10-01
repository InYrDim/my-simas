import type { ReactNode } from 'react';

type Tone = 'stamp' | 'void' | 'pending' | 'muted';

const toneClass: Record<Tone, string> = {
    stamp: 'border-primary/40 bg-primary/10 text-foreground',
    void: 'border-destructive/40 bg-destructive/10 text-foreground',
    pending: 'border-accent/50 bg-accent/10 text-foreground',
    muted: 'border-input bg-muted text-muted-foreground',
};

const statusTone: Record<string, [Tone, string]> = {
    active: ['stamp', 'aktif'],
    suspended: ['void', 'ditangguhkan'],
    paid: ['stamp', 'lunas'],
    unpaid: ['pending', 'belum dibayar'],
    overdue: ['void', 'menunggak'],
    void: ['void', 'dibatalkan'],
    trial: ['muted', 'uji coba'],
    due: ['muted', 'jatuh tempo'],
    cancelled: ['muted', 'berhenti'],
    pending: ['pending', 'pending'],
    inactive: ['muted', 'nonaktif'],
};

export function Chip({ tone, children }: { tone: Tone; children: ReactNode }) {
    return (
        <span
            className={`inline-block rounded-md border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ${toneClass[tone]}`}
        >
            {children}
        </span>
    );
}

/** Status word + tone. Always a word, never colour alone. */
export function StatusChip({ status }: { status: string }) {
    const [tone, label] = statusTone[status] ?? ['muted', status];

    return <Chip tone={tone}>{label}</Chip>;
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
                <h1 className="text-xl font-semibold text-foreground">{title}</h1>
                {description !== undefined && (
                    <p className="mt-1 text-sm text-muted-foreground">{description}</p>
                )}
            </div>
            {actions !== undefined && (
                <div className="flex flex-wrap gap-3">{actions}</div>
            )}
        </div>
    );
}

export function Panel({
    title,
    children,
    className = '',
}: {
    title?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={`console-card rounded-lg border border-border bg-card p-5 ${className}`}
        >
            {title !== undefined && (
                <h2 className="mb-4 text-sm font-semibold text-foreground">
                    {title}
                </h2>
            )}
            {children}
        </section>
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
        <div className="console-card rounded-lg border border-border bg-card p-5">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-2 text-xl font-semibold text-foreground">{value}</p>
            {hint !== undefined && (
                <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
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
        <div className="console-card rounded-lg border border-border bg-card px-6 py-10 text-center text-sm text-muted-foreground">
            {children}
        </div>
    );
}

export const inputClass =
    'min-h-11 w-full rounded-lg border border-input bg-card px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:border-ring focus:outline-none';

export const buttonStamp =
    'inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-4 text-sm font-semibold text-white transition-colors hover:bg-primary/90 active:translate-y-px';

export const buttonGhost =
    'inline-flex min-h-11 items-center justify-center rounded-lg border border-input px-4 text-sm font-medium text-foreground transition-colors hover:bg-muted';

export const buttonVoid =
    'inline-flex min-h-11 items-center justify-center rounded-lg bg-destructive px-4 text-sm font-semibold text-white transition-colors hover:bg-destructive/90';

/**
 * Horizontally scrollable ruled table for the console. Columns are given
 * by the caller; the wrapper owns the border and overflow only.
 */
export function Table({
    head,
    children,
}: {
    head: string[];
    children: ReactNode;
}) {
    return (
        <div className="overflow-x-auto console-card rounded-lg border border-border bg-card">
            <table className="w-full min-w-[40rem] text-left text-sm">
                <thead>
                    <tr className="border-b border-border text-xs text-muted-foreground">
                        {head.map((label) => (
                            <th key={label} className="px-4 py-3 font-medium">
                                {label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-border text-foreground/80">
                    {children}
                </tbody>
            </table>
        </div>
    );
}

/** Single-series bar list: label, bar (the one green), value as text. */
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
                    <span className="h-2 rounded-sm bg-muted">
                        <span
                            className="block h-2 rounded-sm bg-primary"
                            style={{ width: `${(row.value / max) * 100}%` }}
                        />
                    </span>
                    <span className="text-right text-foreground/80">
                        {row.display}
                    </span>
                </li>
            ))}
        </ul>
    );
}
