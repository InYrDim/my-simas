<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Contracts\DTOs\NoticeKind;
use Modules\Core\App\Domain\Models\WhatsappNoticeSetting;
use Modules\Core\App\Infrastructure\Whatsapp\NoticeTemplate;

/**
 * Keep a school's choice for one kind of notice: on or off, and its own
 * wording. Wording that is empty or equal to the kind's default is not
 * stored, so a later change of the default reaches the school. Variation
 * groups `{a|b|c}` in the wording must be well formed; so must the line
 * that asks for a reply, which is kept apart from the wording.
 */
final class SaveNoticeSetting
{
    /**
     * @throws ValidationException when a variation group is malformed
     */
    public function handle(NoticeKind $kind, bool $enabled, ?string $template, bool $replyFooter = false, ?string $replyFooterText = null): WhatsappNoticeSetting
    {
        $template = trim((string) $template);
        $footer = trim((string) $replyFooterText);

        if (($problem = NoticeTemplate::groupError($footer)) !== null) {
            throw ValidationException::withMessages(['reply_footer_text' => $problem]);
        }

        if (($problem = NoticeTemplate::groupError($template)) !== null) {
            throw ValidationException::withMessages(['template' => $problem]);
        }

        return WhatsappNoticeSetting::query()->updateOrCreate(
            ['kind' => $kind->key],
            [
                'enabled' => $enabled,
                'template' => $template === '' || $template === trim($kind->template) ? null : $template,
                'reply_footer' => $replyFooter,
                'reply_footer_text' => $footer === '' || $footer === trim(WhatsappNoticeSetting::DEFAULT_FOOTER) ? null : $footer,
            ],
        );
    }
}
