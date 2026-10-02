<?php

namespace Modules\Ppdb\App\Infrastructure\Notifications;

use Modules\Core\App\Contracts\ContactNotifier;
use Modules\Core\App\Contracts\Exceptions\UnknownNoticeKindException;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Notifications\ResultNotices;
use Modules\Ppdb\App\Domain\Support\ResultAnnouncer;

/**
 * Tells an applicant's guardian the result on WhatsApp, through Core's
 * contact notifier: the school decides whether the notice goes out and how
 * it reads. An applicant without a decision is told nothing. Returns whether
 * a message could be addressed (the guardian's number was given); the result
 * stays on the applicant's own page either way.
 */
final class WhatsappResultAnnouncer implements ResultAnnouncer
{
    public function __construct(
        private readonly ContactNotifier $notifier,
        private readonly ResultNotices $notices,
    ) {}

    public function announce(Applicant $applicant): bool
    {
        if ($applicant->decision === Decision::Pending) {
            return false;
        }

        $path = AdmissionPath::query()->find($applicant->path_id);

        try {
            $this->notifier->notify($this->notices->resultOf($applicant, $path === null ? '' : $path->name));
        } catch (UnknownNoticeKindException) {
            return false;
        }

        return $applicant->guardian_phone !== null && $applicant->guardian_phone !== '';
    }
}
