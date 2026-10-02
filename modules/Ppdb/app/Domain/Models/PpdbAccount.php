<?php

namespace Modules\Ppdb\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Modules\Ppdb\Database\Factories\PpdbAccountFactory;

/**
 * An applicant's own account — a CENTRAL identity, never tenant-scoped (no
 * BelongsToTenant): it exists before the applicant joins a school. It
 * authenticates through the dedicated `ppdb` guard and is not a school
 * user; Identity's users table is untouched.
 *
 * `tenant_id` is the one school the account has joined. It is set and
 * cleared by JoinSchool / LeaveSchool only (it is not fillable).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property Carbon|null $email_verified_at
 * @property string|null $tenant_id
 */
#[UseFactory(PpdbAccountFactory::class)]
#[Fillable(['name', 'email', 'password'])]
class PpdbAccount extends Authenticatable
{
    /** @use HasFactory<PpdbAccountFactory> */
    use HasFactory;

    protected $table = 'ppdb_accounts';

    protected $hidden = ['password', 'remember_token'];

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * The value a verification link must carry: tied to the email, so a
     * link issued for an old address stops working.
     */
    public function verificationHash(): string
    {
        return sha1($this->email);
    }

    /**
     * The value a password-reset link must carry: tied to the email AND the
     * current password, so the link dies the moment the password it was
     * issued against changes — each link works once.
     */
    public function credentialHash(): string
    {
        return sha1($this->email.'|'.$this->password);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
        ];
    }
}
