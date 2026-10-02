<?php

namespace Modules\Platform\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A setting of the provider itself (central, not per tenant), stored as
 * one row per key. Internal to Platform.
 *
 * @property string $key
 * @property mixed $value
 */
#[Fillable(['key', 'value'])]
class ProviderSetting extends Model
{
    /** Approve a school's WhatsApp request without a provider decision. */
    public const WHATSAPP_AUTO_APPROVE = 'whatsapp.auto_approve';

    protected $table = 'provider_settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function read(string $key, mixed $default = null): mixed
    {
        $setting = self::query()->find($key);

        return $setting === null ? $default : $setting->value;
    }

    public static function write(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
