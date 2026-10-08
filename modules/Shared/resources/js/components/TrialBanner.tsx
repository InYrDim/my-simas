import { usePage } from '@inertiajs/react';

import { Button } from '@shared/components/ui/button';
import { formatDate } from '@shared/lib/billing';
import { cn } from '@shared/lib/utils';
import type { TrialNotice } from '../types/billing';

/** Days left at or below which the banner turns from calm to urgent. */
const URGENT_DAYS = 3;

/**
 * A strip under the top bar while the school is still on its free trial,
 * and just after it ends (the grace period, when access still runs but is
 * about to stop). Every signed-in user of the school sees it; only someone
 * who may see billing gets the button that opens the plan panel, the rest
 * are told to ask the school admin.
 */
export default function TrialBanner({
    onOpenPlan,
}: {
    /** Opens "Paket & Langganan"; omit for a user who may not see billing. */
    onOpenPlan?: () => void;
}) {
    const { trial } = usePage<{ trial?: TrialNotice | null }>().props;

    if (!trial) {
        return null;
    }

    const expired = trial.state === 'trial_expired';
    const urgent = expired || trial.daysLeft <= URGENT_DAYS;

    return (
        <div
            role="status"
            data-testid="trial-banner"
            className={cn(
                'flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-border px-4 py-2 text-sm sm:px-6',
                expired && 'bg-destructive/10',
                !expired && urgent && 'bg-accent/30',
                !urgent && 'bg-primary/10',
            )}
        >
            <p>
                <span className="font-medium">{message(trial)}</span>{' '}
                <span className="text-muted-foreground">
                    {onOpenPlan
                        ? 'Pilih paket agar sekolah tetap bisa memakai SIMAS.'
                        : 'Minta admin sekolah memilih paket agar SIMAS tetap bisa dipakai.'}
                </span>
            </p>

            {onOpenPlan && (
                <Button size="sm" variant="outline" onClick={onOpenPlan}>
                    Lihat paket
                </Button>
            )}
        </div>
    );
}

function message(trial: TrialNotice): string {
    if (trial.state === 'trial_expired') {
        return `Masa uji coba berakhir ${formatDate(trial.endsOn)}${
            trial.accessEndsOn
                ? `; akses tetap berjalan sampai ${formatDate(trial.accessEndsOn)}.`
                : '.'
        }`;
    }

    if (trial.daysLeft <= 0) {
        return 'Masa uji coba berakhir hari ini.';
    }

    return `Masa uji coba berakhir ${trial.daysLeft} hari lagi (${formatDate(trial.endsOn)}).`;
}
