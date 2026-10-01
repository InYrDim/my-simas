<?php

namespace Modules\Platform\App\Domain\Models;

/**
 * Stored subscription status. Date-derived states (due, overdue, trial
 * expired) are NOT stored — see Subscription::displayState().
 */
enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Cancelled = 'cancelled';
}
