import { Link } from '@inertiajs/react';

import {
    BarList,
    EmptyState,
    Panel,
    StatCard,
} from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Skeleton } from '@shared/components/ui/skeleton';

type ListItem = {
    label: string;
    detail?: string;
    /** A state, spelled out: shown as a badge. */
    status?: string;
    /** A plain figure, such as a count: shown as text, not a badge. */
    value?: string;
    href?: string;
};

type StatusState = 'confirmed' | 'pending' | 'void' | 'none';

type WidgetBase = { key: string; title: string; href: string | null };

export type DashboardWidget = WidgetBase &
    (
        | { kind: 'stat'; payload: { value: string | number; hint?: string } }
        | {
              kind: 'list';
              payload: { items: ListItem[]; empty: string; total?: number };
          }
        | {
              kind: 'bars';
              payload: {
                  points: { label: string; value: number }[];
                  note?: string;
              };
          }
        | {
              kind: 'status';
              payload: { state: StatusState; word: string; detail?: string };
          }
        | { kind: 'action'; payload: { label: string } }
    );

export type DashboardSlots = Partial<
    Record<'action' | 'figures' | 'attention' | 'main', DashboardWidget[]>
>;

const stateVariant = {
    confirmed: 'default',
    pending: 'outline',
    void: 'destructive',
    none: 'secondary',
} as const;

/** Rows are at least 44px tall: the primary user taps with a thumb. */
const rowClass =
    'flex min-h-11 items-center justify-between gap-4 rounded-md py-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';

function ListWidget({
    items,
    empty,
    total,
    href,
}: {
    items: ListItem[];
    empty: string;
    total?: number;
    href: string | null;
}) {
    if (items.length === 0) {
        return <p className="text-sm text-muted-foreground">{empty}</p>;
    }

    const more = total !== undefined && total > items.length;

    return (
        <>
            <ul className="border-t border-border">
                {items.map((item) => {
                    const body = (
                        <>
                            <span className="min-w-0 text-foreground">
                                {item.label}
                                {item.detail !== undefined && (
                                    <span className="block text-xs text-muted-foreground">
                                        {item.detail}
                                    </span>
                                )}
                            </span>
                            {item.value !== undefined && (
                                <span className="text-sm text-foreground tabular-nums">
                                    {item.value}
                                </span>
                            )}
                            {item.status !== undefined && (
                                <Badge
                                    variant="outline"
                                    className="tabular-nums"
                                >
                                    {item.status}
                                </Badge>
                            )}
                        </>
                    );

                    return (
                        <li
                            key={`${item.label}-${item.detail ?? ''}`}
                            className="border-b border-border"
                        >
                            {item.href !== undefined ? (
                                <Link href={item.href} className={rowClass}>
                                    {body}
                                </Link>
                            ) : (
                                <div className={rowClass}>{body}</div>
                            )}
                        </li>
                    );
                })}
            </ul>
            {href !== null && more && (
                <Link
                    href={href}
                    className="mt-2 flex min-h-11 items-center text-sm font-medium text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    Lihat semua ({total})
                </Link>
            )}
        </>
    );
}

function WidgetBody({ widget }: { widget: DashboardWidget }) {
    switch (widget.kind) {
        case 'list':
            return (
                <Panel title={widget.title}>
                    <ListWidget {...widget.payload} href={widget.href} />
                </Panel>
            );
        case 'bars':
            return (
                <Panel title={widget.title}>
                    {widget.payload.points.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Belum ada data.
                        </p>
                    ) : (
                        <BarList
                            rows={widget.payload.points.map((point) => ({
                                ...point,
                                display: String(point.value),
                            }))}
                        />
                    )}
                    {widget.payload.note !== undefined && (
                        <p className="mt-3 text-xs text-muted-foreground">
                            {widget.payload.note}
                        </p>
                    )}
                </Panel>
            );
        case 'status':
            return (
                <Panel title={widget.title}>
                    <div className="flex items-center gap-3">
                        <Badge variant={stateVariant[widget.payload.state]}>
                            {widget.payload.word}
                        </Badge>
                        {widget.payload.detail !== undefined && (
                            <span className="text-sm text-muted-foreground">
                                {widget.payload.detail}
                            </span>
                        )}
                    </div>
                </Panel>
            );
        default:
            return null;
    }
}

/** Placeholder while the deferred widgets load: same bands, no content. */
export function DashboardSkeleton({ wide }: { wide: boolean }) {
    return (
        <div
            className="mt-8 flex flex-col gap-4"
            role="status"
            aria-busy="true"
            aria-label="Memuat ringkasan"
        >
            <div
                className={`grid grid-cols-2 gap-3 ${wide ? 'sm:grid-cols-4' : 'sm:grid-cols-2'}`}
            >
                <Skeleton className="h-20" />
                <Skeleton className="h-20" />
                <Skeleton className="h-20 max-sm:hidden" />
                <Skeleton className="h-20 max-sm:hidden" />
            </div>
            <Skeleton className="h-32" />
        </div>
    );
}

/** The hrefs of the action widgets, so a shortcut to the same place is not shown twice. */
export function actionHrefs(widgets: DashboardSlots | undefined): string[] {
    return (widgets?.action ?? []).flatMap((widget) =>
        widget.href === null ? [] : [widget.href],
    );
}

/**
 * The one thing to do first: the action widgets, as full-width buttons.
 * Nothing while the widgets load; the bands below hold the skeleton.
 */
export function DashboardActions({
    widgets,
}: {
    widgets: DashboardSlots | undefined;
}) {
    const actions = widgets?.action ?? [];

    if (actions.length === 0) {
        return null;
    }

    return (
        <div className="mt-5 flex flex-col gap-3">
            {actions.map((widget, index) =>
                widget.kind === 'action' && widget.href !== null ? (
                    <Button
                        key={widget.key}
                        asChild
                        variant={index > 0 ? 'outline' : 'default'}
                        className="min-h-11 w-full"
                    >
                        <Link href={widget.href}>{widget.payload.label}</Link>
                    </Button>
                ) : null,
            )}
        </div>
    );
}

/**
 * The blocks the active modules registered for this person, one band per
 * slot: the figures, what needs attention, and the rest (the one action
 * is `DashboardActions`, above them). `wide` is for the office desktop:
 * four figures to a row and the panels in two columns. Core knows the
 * shapes (`kind`), never the modules behind them.
 */
export default function DashboardWidgets({
    widgets,
    wide,
}: {
    widgets: DashboardSlots | undefined;
    wide: boolean;
}) {
    if (widgets === undefined) {
        return <DashboardSkeleton wide={wide} />;
    }

    const { figures = [], attention = [], main = [] } = widgets;
    const panels = [...attention, ...main];

    if (figures.length + panels.length === 0) {
        return null;
    }

    return (
        <div className="mt-8 flex flex-col gap-4">
            {figures.length > 0 && (
                <div
                    className={`grid grid-cols-2 gap-3 ${wide ? 'sm:grid-cols-4' : 'sm:grid-cols-2'}`}
                >
                    {figures.map((widget) =>
                        widget.kind === 'stat' ? (
                            <StatCard
                                key={widget.key}
                                label={widget.title}
                                value={widget.payload.value}
                                hint={widget.payload.hint}
                            />
                        ) : null,
                    )}
                </div>
            )}

            <div
                className={
                    wide
                        ? 'grid items-start gap-4 sm:grid-cols-2'
                        : 'flex flex-col gap-4'
                }
            >
                {panels.map((widget) => (
                    <WidgetBody key={widget.key} widget={widget} />
                ))}
            </div>
        </div>
    );
}
