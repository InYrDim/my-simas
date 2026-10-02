<?php

namespace Modules\Ppdb\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;
use Modules\Ppdb\Database\Factories\AdmissionWaveFactory;

/**
 * A registration wave inside a period. The dates are days of the school
 * (`Y-m-d`); whether a wave is upcoming, open or closed is worked out from
 * the school's today and never stored.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $period_id
 * @property string $name
 * @property string $opens_on Y-m-d, first day applicants may register
 * @property string $closes_on Y-m-d, last day applicants may register
 */
#[UseFactory(AdmissionWaveFactory::class)]
#[Fillable(['period_id', 'name', 'opens_on', 'closes_on'])]
class AdmissionWave extends Model
{
    /** @use HasFactory<AdmissionWaveFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'ppdb_waves';

    public const UPCOMING = 'upcoming';

    public const OPEN = 'open';

    public const CLOSED = 'closed';

    /**
     * `Y-m-d`, whatever the database hands back (SQLite and MySQL differ).
     */
    public function getOpensOnAttribute(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 10);
    }

    /**
     * `Y-m-d`, whatever the database hands back (SQLite and MySQL differ).
     */
    public function getClosesOnAttribute(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 10);
    }

    /**
     * `upcoming`, `open` or `closed` on the given day (`Y-m-d`).
     */
    public function statusOn(string $today): string
    {
        return match (true) {
            $today < $this->opens_on => self::UPCOMING,
            $today > $this->closes_on => self::CLOSED,
            default => self::OPEN,
        };
    }
}
