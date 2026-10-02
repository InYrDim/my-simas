<?php

namespace Modules\Ppdb\App\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;
use Modules\Ppdb\App\Domain\Enums\FieldRequirement;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Support\FormFields;
use Modules\Ppdb\Database\Factories\AdmissionPeriodFactory;

/**
 * One admissions period of a school ("PPDB 2027/2028"). A school has at
 * most one active period; its waves and paths belong to it.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property int $entry_year the year the new students start
 * @property PeriodStatus $status
 * @property array<string, string>|null $form_fields requirement by field key; null is the usual form
 * @property CarbonInterface|null $results_published_at
 */
#[UseFactory(AdmissionPeriodFactory::class)]
#[Fillable(['name', 'entry_year', 'status', 'form_fields', 'results_published_at'])]
class AdmissionPeriod extends Model
{
    /** @use HasFactory<AdmissionPeriodFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'ppdb_periods';

    /**
     * The current school's active period, if it has one.
     */
    public static function active(): ?self
    {
        return self::query()->where('status', PeriodStatus::Active->value)->first();
    }

    /**
     * What this period's registration form asks for, by field key.
     *
     * @return array<string, FieldRequirement>
     */
    public function formFields(): array
    {
        return FormFields::resolve($this->form_fields);
    }

    /**
     * @return HasMany<AdmissionWave, $this>
     */
    public function waves(): HasMany
    {
        return $this->hasMany(AdmissionWave::class, 'period_id')->orderBy('opens_on')->orderBy('id');
    }

    /**
     * @return HasMany<AdmissionPath, $this>
     */
    public function paths(): HasMany
    {
        return $this->hasMany(AdmissionPath::class, 'period_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_year' => 'integer',
            'status' => PeriodStatus::class,
            'form_fields' => 'array',
            'results_published_at' => 'datetime',
        ];
    }
}
