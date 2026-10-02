<?php

namespace Modules\Core\App\Contracts;

use Modules\Core\App\Contracts\DTOs\GuardianNotice;
use Modules\Core\App\Contracts\Exceptions\UnknownNoticeKindException;

/**
 * Sends a WhatsApp notice to a student's guardian from the school's own
 * number. Works on the school in the current tenant context.
 *
 * The caller only says what happened; whether a message goes out is the
 * school's decision. Nothing is sent, and nothing is logged, while the
 * school has the kind switched off. When it is on, the message is logged
 * and queued even if it cannot be delivered (no guardian number, WhatsApp
 * not linked): the log says why.
 */
interface GuardianNotifier
{
    /**
     * @throws UnknownNoticeKindException the kind is not registered, or its module is not active for the school
     */
    public function notify(GuardianNotice $notice): void;
}
