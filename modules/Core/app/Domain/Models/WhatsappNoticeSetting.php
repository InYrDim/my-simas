<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
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
 */
#[Fillable(['kind', 'enabled', 'template'])]
class WhatsappNoticeSetting extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
