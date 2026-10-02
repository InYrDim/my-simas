<?php

namespace Modules\Ppdb\Tests\Feature\Support;

use Modules\Core\App\Contracts\ContactNotifier;
use Modules\Core\App\Contracts\DTOs\ContactNotice;
use Modules\Core\App\Contracts\Exceptions\UnknownNoticeKindException;

/**
 * Remembers what Ppdb handed to Core's contact notifier, to prove who is
 * told what. It sends nothing; set `refuse` to behave like a school whose
 * PPDB notice kind is not available.
 */
final class RecordingContactNotifier implements ContactNotifier
{
    /**
     * @var list<ContactNotice>
     */
    public array $sent = [];

    public bool $refuse = false;

    public function notify(ContactNotice $notice): void
    {
        if ($this->refuse) {
            throw new UnknownNoticeKindException($notice->kind);
        }

        $this->sent[] = $notice;
    }
}
