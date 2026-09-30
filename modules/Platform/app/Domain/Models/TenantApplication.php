<?php

namespace Modules\Platform\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Platform\Database\Factories\TenantApplicationFactory;

/**
 * Internal Platform model — never exposed to other modules. Central
 * onboarding row: the applicant is NOT a user (identity columns here);
 * decided_by references provider_users within this module.
 *
 * @property int $id
 * @property string $school_name
 * @property string $desired_slug
 * @property string $timezone
 * @property string $applicant_name
 * @property string $applicant_email
 * @property string|null $applicant_message
 * @property TenantApplicationStatus $status
 * @property string|null $admin_note
 * @property Carbon|null $decided_at
 * @property int|null $decided_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(TenantApplicationFactory::class)]
#[Fillable([
    'school_name', 'desired_slug', 'timezone', 'applicant_name',
    'applicant_email', 'applicant_message', 'status', 'admin_note',
    'decided_at', 'decided_by',
])]
class TenantApplication extends Model
{
    /** @use HasFactory<TenantApplicationFactory> */
    use HasFactory;

    protected $table = 'tenant_applications';

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantApplicationStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * The provider staff member who decided this application.
     *
     * @return BelongsTo<ProviderUser, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(ProviderUser::class, 'decided_by');
    }
}
