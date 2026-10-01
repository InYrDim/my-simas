<?php

namespace Modules\Platform\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Modules\Platform\Database\Factories\ApplicantFactory;

/**
 * Someone applying to bring a school onto the platform — a CENTRAL
 * identity, never tenant-scoped (no BelongsToTenant): the applicant
 * exists before the tenant. Authenticates via the dedicated 'applicant'
 * guard and is not a school user; Identity's users table is untouched
 * until approval provisions the school admin.
 *
 * NOT exposed to other modules — like ProviderUser, this is Platform's
 * own onboarding surface.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property Carbon|null $email_verified_at
 * @property string|null $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(ApplicantFactory::class)]
#[Fillable(['name', 'email', 'password'])]
class Applicant extends Authenticatable
{
    /** @use HasFactory<ApplicantFactory> */
    use HasFactory;

    protected $hidden = ['password', 'remember_token'];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
        ];
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * The value a verification link must carry for this applicant: tied
     * to the email, so a link issued for an old address stops working.
     */
    public function verificationHash(): string
    {
        return sha1($this->email);
    }
}
