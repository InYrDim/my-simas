<?php

namespace Modules\Platform\App\Domain\Models;

/**
 * What an invoice is for; persisted as its string value.
 */
enum InvoiceKind: string
{
    /** The first paid period after a trial (or after a cancellation). */
    case Activation = 'activation';

    /** The next period on the current plan and cycle. */
    case Renewal = 'renewal';

    /** The prorated difference for moving to a dearer plan mid-period. */
    case Upgrade = 'upgrade';
}
