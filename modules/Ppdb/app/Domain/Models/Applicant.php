<?php

namespace Modules\Ppdb\App\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;
use Modules\Ppdb\App\Domain\Enums\ApplicantSource;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\Database\Factories\ApplicantFactory;

/**
 * Someone applying to the school in an admissions period. `account_id` is
 * the applicant's PPDB account (central) and `student_id` the student made
 * at re-registration; both are plain ids. Days are `Y-m-d` strings.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $period_id
 * @property int $wave_id
 * @property int $path_id
 * @property int|null $account_id
 * @property string $number
 * @property string $name
 * @property string $gender L|P
 * @property string|null $birth_place
 * @property string $birth_date Y-m-d
 * @property string|null $nisn
 * @property string $origin_school
 * @property string|null $address
 * @property string $guardian_name
 * @property string $guardian_phone
 * @property ApplicantSource $source
 * @property string $registered_on Y-m-d
 * @property ApplicantStatus $status
 * @property string|null $verification_note
 * @property string|null $score
 * @property Decision $decision
 * @property CarbonInterface|null $enrolled_at
 * @property int|null $student_id
 * @property int|null $recorded_by
 */
#[UseFactory(ApplicantFactory::class)]
#[Fillable([
    'period_id', 'wave_id', 'path_id', 'account_id', 'number', 'name', 'gender', 'birth_place', 'birth_date',
    'nisn', 'origin_school', 'address', 'guardian_name', 'guardian_phone', 'source', 'registered_on', 'status',
    'verification_note', 'score', 'decision', 'enrolled_at', 'student_id', 'recorded_by',
])]
class Applicant extends Model
{
    /** @use HasFactory<ApplicantFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * The fields an applicant (or the committee on their behalf) fills in.
     *
     * @var list<string>
     */
    public const DATA_FIELDS = [
        'wave_id', 'path_id', 'name', 'gender', 'birth_place', 'birth_date', 'nisn',
        'origin_school', 'address', 'guardian_name', 'guardian_phone',
    ];

    protected $table = 'ppdb_applicants';

    /**
     * `Y-m-d`, whatever the database hands back (SQLite and MySQL differ).
     */
    public function getBirthDateAttribute(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 10);
    }

    /**
     * `Y-m-d`, whatever the database hands back (SQLite and MySQL differ).
     */
    public function getRegisteredOnAttribute(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 10);
    }

    public function isEnrolled(): bool
    {
        return $this->enrolled_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => ApplicantSource::class,
            'status' => ApplicantStatus::class,
            'decision' => Decision::class,
            'enrolled_at' => 'datetime',
        ];
    }
}
