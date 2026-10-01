<?php

namespace Modules\Platform\App\Domain\Models;

/**
 * Subscription billing cycle; persisted as its string value.
 */
enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /**
     * Length of one billing period, in months.
     */
    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Yearly => 12,
        };
    }
}
