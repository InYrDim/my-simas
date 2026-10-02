import { Panel, StatCard } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { cn } from '@shared/lib/utils';

import MasterPage from '../../../Components/MasterPage';
import type { SchoolSummary } from '../../../types/master';

interface Figure {
    key: string;
    label: string;
    value: number | string | null;
    hint: string | null;
    /** False for a figure that is announced but that no module provides yet. */
    available: boolean;
}

interface Point {
    label: string;
    value: number;
}

interface StatPanel {
    key: string;
    title: string;
    kind: 'bars' | 'share' | null;
    points: Point[];
    note: string | null;
    available: boolean;
}

interface StatisticsProps {
    school: SchoolSummary;
    /** Name of the academic year the figures are for; null when the school has none. */
    period: string | null;
    figures: Figure[];
    panels: StatPanel[];
}

const shareColors = ['bg-primary', 'bg-chart-3', 'bg-chart-2', 'bg-chart-4'];

const format = (value: number | string) =>
    typeof value === 'number' ? value.toLocaleString('id-ID') : value;

function NoData() {
    return <p className="text-sm text-muted-foreground">Belum ada data.</p>;
}

/** One bar per point, scaled to the largest. */
function Bars({ points }: { points: Point[] }) {
    const widest = Math.max(...points.map((point) => point.value), 0);

    if (widest === 0) {
        return <NoData />;
    }

    return (
        <ul className="flex flex-col gap-3">
            {points.map((point) => (
                <li key={point.label}>
                    <div className="flex justify-between text-sm">
                        <span>{point.label}</span>
                        <span className="font-medium">{format(point.value)}</span>
                    </div>
                    <div
                        role="progressbar"
                        aria-label={point.label}
                        aria-valuenow={point.value}
                        aria-valuemin={0}
                        aria-valuemax={widest}
                        className="mt-1.5 h-2 bg-muted"
                    >
                        <div
                            className="h-full bg-primary"
                            style={{ width: `${Math.round((point.value / widest) * 100)}%` }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}

/** The points as parts of one whole: a split bar and the number of each part. */
function Share({ points }: { points: Point[] }) {
    const total = points.reduce((sum, point) => sum + point.value, 0);

    if (total === 0) {
        return <NoData />;
    }

    return (
        <>
            <div
                role="img"
                aria-label={points.map((point) => `${point.label} ${point.value}`).join(', ')}
                className="flex h-3 overflow-hidden"
            >
                {points.map((point, index) => (
                    <div
                        key={point.label}
                        className={shareColors[index % shareColors.length]}
                        style={{ width: `${(point.value / total) * 100}%` }}
                    />
                ))}
            </div>
            <dl className="mt-4 grid grid-cols-2 gap-4 text-sm">
                {points.map((point, index) => (
                    <div key={point.label}>
                        <dt className="flex items-center gap-2 text-muted-foreground">
                            <span
                                className={cn('size-2.5', shareColors[index % shareColors.length])}
                                aria-hidden
                            />
                            {point.label}
                        </dt>
                        <dd className="mt-1 text-xl font-semibold">{format(point.value)}</dd>
                    </div>
                ))}
            </dl>
        </>
    );
}

/** Statistik: the school in numbers — headcounts and how the students spread. */
export default function Statistics({ school, period, figures, panels }: StatisticsProps) {
    return (
        <MasterPage
            school={school}
            title="Statistik"
            description={
                period === null
                    ? 'Gambaran singkat sekolah. Belum ada tahun ajaran.'
                    : `Gambaran singkat sekolah pada tahun ajaran ${period}.`
            }
            width="max-w-6xl"
            mock={false}
        >
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                {figures.map((figure) =>
                    figure.available && figure.value !== null ? (
                        <StatCard
                            key={figure.key}
                            label={figure.label}
                            value={format(figure.value)}
                            hint={figure.hint ?? undefined}
                        />
                    ) : (
                        <div key={figure.key} className="opacity-60">
                            <StatCard label={figure.label} value="—" hint="Segera hadir" />
                        </div>
                    ),
                )}
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                {panels.map((panel) => (
                    <Panel key={panel.key} title={panel.title}>
                        {!panel.available ? (
                            <div className="flex flex-col items-start gap-2">
                                <Badge variant="secondary">Segera hadir</Badge>
                                <p className="text-sm text-muted-foreground">
                                    Data ini belum tersedia untuk sekolah Anda.
                                </p>
                            </div>
                        ) : (
                            <>
                                {panel.kind === 'share' ? (
                                    <Share points={panel.points} />
                                ) : (
                                    <Bars points={panel.points} />
                                )}
                                {panel.note !== null && (
                                    <p className="mt-4 text-xs text-muted-foreground">{panel.note}</p>
                                )}
                            </>
                        )}
                    </Panel>
                ))}
            </div>
        </MasterPage>
    );
}
