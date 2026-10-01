<?php

namespace Modules\Core\App\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\SemesterFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * A Ganjil or Genap half of an academic year. Whether it is running is
 * derived from its dates and its year's status, never stored.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $academic_year_id
 * @property string $name
 * @property Carbon $start_date
 * @property Carbon $end_date
 */
#[UseFactory(SemesterFactory::class)]
#[Fillable(['name', 'start_date', 'end_date'])]
class Semester extends Model
{
    /** @use HasFactory<SemesterFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return 'current'|'upcoming'|'finished'
     */
    public function statusOn(CarbonInterface $today): string
    {
        $day = $today->toDateString();
        $start = $this->start_date->toDateString();
        $end = $this->end_date->toDateString();

        return match (true) {
            $this->academicYear->isActive() && $day >= $start && $day <= $end => 'current',
            $day < $start => 'upcoming',
            default => 'finished',
        };
    }

    public function weeks(): int
    {
        return (int) ceil(($this->start_date->diffInDays($this->end_date, true) + 1) / 7);
    }

    public function weekOn(CarbonInterface $today): int
    {
        return (int) floor($this->start_date->diffInDays($today->startOfDay(), true) / 7) + 1;
    }
}
