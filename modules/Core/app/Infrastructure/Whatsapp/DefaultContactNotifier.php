<?php

namespace Modules\Core\App\Infrastructure\Whatsapp;

use Modules\Core\App\Contracts\ContactNotifier;
use Modules\Core\App\Contracts\DTOs\ContactNotice;
use Modules\Core\App\Contracts\Exceptions\UnknownNoticeKindException;
use Modules\Core\App\Domain\Models\WhatsappNoticeSetting;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Default ContactNotifier: like the guardian notifier, the school's switch
 * for the kind decides whether anything happens and the wording is the
 * school's own when it has one; the name and number come from the caller and
 * the message leaves through the same log and queue as every other.
 */
final class DefaultContactNotifier implements ContactNotifier
{
    public function __construct(
        private readonly DefaultNoticeRegistry $registry,
        private readonly QueueWhatsappMessage $queue,
        private readonly VariationPicker $variation,
        private readonly TenantContext $context,
    ) {}

    public function notify(ContactNotice $notice): void
    {
        $kind = $this->registry->find($notice->kind) ?? throw new UnknownNoticeKindException($notice->kind);
        $setting = WhatsappNoticeSetting::query()->where('kind', $kind->key)->first();

        if ($setting === null || ! $setting->enabled) {
            return;
        }

        $this->queue->handle(
            $kind->key,
            $notice->recipientName,
            $notice->phone,
            $this->variation->fill($setting->wording($kind), [
                ...$notice->variables,
                'nama_siswa' => $notice->subjectName,
                'nama_wali' => $notice->recipientName,
                'nama_sekolah' => $this->context->currentOrFail()->name,
            ]),
        );
    }
}
