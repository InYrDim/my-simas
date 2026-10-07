<?php

namespace Modules\Core\App\Infrastructure\Whatsapp;

use Modules\Core\App\Contracts\DTOs\GuardianNotice;
use Modules\Core\App\Contracts\Exceptions\UnknownNoticeKindException;
use Modules\Core\App\Contracts\GuardianNotifier;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\WhatsappNoticeSetting;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Default GuardianNotifier: the school's switch for the kind decides
 * whether anything happens; the wording is the school's own when it has
 * one; the guardian's name and number come from the student record; and
 * the message leaves through the same log and queue as every other.
 */
final class DefaultGuardianNotifier implements GuardianNotifier
{
    public function __construct(
        private readonly DefaultNoticeRegistry $registry,
        private readonly QueueWhatsappMessage $queue,
        private readonly VariationPicker $variation,
        private readonly TenantContext $context,
    ) {}

    public function notify(GuardianNotice $notice): void
    {
        $kind = $this->registry->find($notice->kind) ?? throw new UnknownNoticeKindException($notice->kind);
        $setting = WhatsappNoticeSetting::query()->where('kind', $kind->key)->first();

        if ($setting === null || ! $setting->enabled) {
            return;
        }

        // Tenant-scoped: a student id of another school is simply not found.
        $student = Student::query()->find($notice->studentId);

        if ($student === null) {
            return;
        }

        $guardian = $student->guardian_name ?? "Wali {$student->name}";

        $this->queue->handle(
            $kind->key,
            $guardian,
            $student->guardian_phone,
            $this->variation->fill($setting->wording($kind), [
                ...$notice->variables,
                'nama_siswa' => $student->name,
                'nama_wali' => $guardian,
                'nama_sekolah' => $this->context->currentOrFail()->name,
            ]),
            $student->id,
        );
    }
}
