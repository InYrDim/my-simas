<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\App\Domain\Enums\SchoolLevel;
use Modules\Core\Database\Factories\SchoolProfileFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * The school's own record: one row per tenant, created on first use
 * (the school code, name and timezone stay with Platform's tenant).
 *
 * @property int $id
 * @property string $tenant_id
 * @property SchoolLevel $level
 * @property string|null $npsn
 * @property string|null $ownership
 * @property string|null $accreditation
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $headmaster
 * @property string|null $headmaster_nip
 */
#[UseFactory(SchoolProfileFactory::class)]
#[Fillable(['level', 'npsn', 'ownership', 'accreditation', 'address', 'phone', 'email', 'headmaster', 'headmaster_nip'])]
class SchoolProfile extends Model
{
    /** @use HasFactory<SchoolProfileFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['level' => SchoolLevel::class];
    }

    /**
     * The current tenant's profile, created with defaults when missing.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate([], ['level' => SchoolLevel::Sma]);
    }
}
