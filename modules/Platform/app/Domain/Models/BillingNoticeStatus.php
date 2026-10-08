<?php

namespace Modules\Platform\App\Domain\Models;

/**
 * Delivery state of a billing notice. Queued is set when the mail is
 * handed to the queue; Failed also covers "no billing contact".
 */
enum BillingNoticeStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
