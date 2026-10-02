<?php

namespace Modules\Core\App\Domain\Enums;

/**
 * What became of a WhatsApp message. Only `Pending` changes later.
 */
enum WhatsappMessageStatus: string
{
    /** Waiting in the queue. */
    case Pending = 'pending';

    /** Accepted by WhatsApp for delivery (not a delivery receipt). */
    case Sent = 'sent';

    /** The gateway refused it, or stayed down through every attempt. */
    case Failed = 'failed';

    /** The school's WhatsApp was not linked when its turn came. */
    case Unsent = 'unsent';

    /** There was no usable number to send it to. */
    case NoRecipient = 'no_recipient';
}
