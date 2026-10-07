<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\App\Contracts\DTOs\NoticeKind;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * A school's choice for one kind of WhatsApp notice. Stored in
 * `whatsapp_notice_settings`, one row per school and kind; no row means
 * the kind is off and uses its default wording.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $kind key of a registered notice kind
 * @property bool $enabled
 * @property string|null $template the school's wording; null = the kind's default
 * @property bool $reply_footer end the message with a line asking the guardian to reply
 * @property string|null $reply_footer_text the school's own line; null = DEFAULT_FOOTER
 */
#[Fillable(['kind', 'enabled', 'template', 'reply_footer', 'reply_footer_text'])]
class WhatsappNoticeSetting extends Model
{
    use BelongsToTenant;

    /**
     * The line that asks for a reply: a guardian who answers makes the
     * school's number look like a person, not a bot. It may use
     * `{a|b|c}` variations like any wording.
     */
    public const DEFAULT_FOOTER = '{Mohon|Silakan} balas pesan ini dengan "OK" {jika|bila} pesan sudah diterima.';

    /**
     * The full wording of a message of this kind: the school's own text or
     * the kind's default, then the reply line when the school wants one.
     */
    public function wording(NoticeKind $kind): string
    {
        $wording = $this->template ?? $kind->template;

        if (! $this->reply_footer) {
            return $wording;
        }

        $footer = trim((string) $this->reply_footer_text);

        return $wording."\n\n".($footer === '' ? self::DEFAULT_FOOTER : $footer);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'reply_footer' => 'boolean'];
    }
}
