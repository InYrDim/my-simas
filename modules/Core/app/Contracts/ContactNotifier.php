<?php

namespace Modules\Core\App\Contracts;

use Modules\Core\App\Contracts\DTOs\ContactNotice;
use Modules\Core\App\Contracts\Exceptions\UnknownNoticeKindException;

/**
 * Sends a WhatsApp notice from the school's own number to someone who has no
 * student record to look up: the caller gives the name and the number.
 * Works on the school in the current tenant context.
 *
 * The same rules as `GuardianNotifier`: the caller only says what happened;
 * whether a message goes out is the school's decision. Nothing is sent, and
 * nothing is logged, while the school has the kind switched off. When it is
 * on, the message is logged and queued even if it cannot be delivered (no
 * number, WhatsApp not linked): the log says why.
 */
interface ContactNotifier
{
    /**
     * @throws UnknownNoticeKindException the kind is not registered, or its module is not active for the school
     */
    public function notify(ContactNotice $notice): void;
}
