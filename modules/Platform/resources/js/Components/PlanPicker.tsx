import { CheckIcon } from 'lucide-react';

import { Badge } from '@shared/components/ui/badge';
import { cn } from '@shared/lib/utils';

import type { PlanOption } from '../types/ApplicationData';
import { formatRupiah } from './format';

/**
 * Single-choice list of plans for the onboarding form. Each plan is a
 * native radio input wrapped in a card-sized label, so keyboard and
 * screen-reader behaviour come from the browser; the shared primitives
 * have no radio group to build on.
 */
export default function PlanPicker({
    plans,
    value,
    onChange,
    trialDays,
    invalid = false,
}: {
    plans: PlanOption[];
    value: string;
    onChange: (key: string) => void;
    trialDays: number;
    invalid?: boolean;
}) {
    return (
        <div
            role="radiogroup"
            aria-label="Paket"
            aria-invalid={invalid}
            className="flex flex-col gap-3"
        >
            {plans.map((plan) => {
                const selected = plan.key === value;

                return (
                    <label
                        key={plan.key}
                        className={cn(
                            'flex cursor-pointer items-start gap-3 border bg-card px-4 py-3 transition-colors focus-within:ring-2 focus-within:ring-ring',
                            selected
                                ? 'border-primary'
                                : 'border-border hover:bg-muted',
                            invalid && !selected && 'border-destructive',
                        )}
                    >
                        <input
                            type="radio"
                            name="plan_key"
                            value={plan.key}
                            checked={selected}
                            onChange={() => onChange(plan.key)}
                            className="sr-only"
                        />

                        <span
                            aria-hidden
                            className={cn(
                                'mt-0.5 flex size-5 shrink-0 items-center justify-center border',
                                selected
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border',
                            )}
                        >
                            {selected && <CheckIcon className="size-3.5" />}
                        </span>

                        <span className="flex flex-1 flex-col gap-1">
                            <span className="flex flex-wrap items-center justify-between gap-2">
                                <span className="font-medium">{plan.name}</span>
                                <Badge variant="secondary">
                                    Trial {trialDays} hari
                                </Badge>
                            </span>
                            <span className="text-sm text-muted-foreground">
                                {formatRupiah(plan.priceMonthly)}/bulan ·{' '}
                                {formatRupiah(plan.priceYearly)}/tahun
                            </span>
                            <span className="text-sm text-muted-foreground">
                                {plan.maxUsers === null
                                    ? 'Pengguna tanpa batas'
                                    : `Hingga ${plan.maxUsers} pengguna`}
                            </span>
                        </span>
                    </label>
                );
            })}
        </div>
    );
}
