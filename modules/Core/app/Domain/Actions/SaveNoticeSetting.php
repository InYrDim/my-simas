<?php

namespace Modules\Core\App\Domain\Actions;

use Modules\Core\App\Contracts\DTOs\NoticeKind;
use Modules\Core\App\Domain\Models\WhatsappNoticeSetting;

/**
 * Keep a school's choice for one kind of notice: on or off, and its own
 * wording. Wording that is empty or equal to the kind's default is not
 * stored, so a later change of the default reaches the school.
 */
final class SaveNoticeSetting
{
    public function handle(NoticeKind $kind, bool $enabled, ?string $template): WhatsappNoticeSetting
    {
        $template = trim((string) $template);

        return WhatsappNoticeSetting::query()->updateOrCreate(
            ['kind' => $kind->key],
            [
                'enabled' => $enabled,
                'template' => $template === '' || $template === trim($kind->template) ? null : $template,
            ],
        );
    }
}
