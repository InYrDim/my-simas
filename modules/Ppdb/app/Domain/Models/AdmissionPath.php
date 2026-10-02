<?php

namespace Modules\Ppdb\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;
use Modules\Ppdb\Database\Factories\AdmissionPathFactory;

/**
 * An admission path (jalur: Zonasi, Prestasi, ...) of a period, with the
 * number of seats it offers.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $period_id
 * @property string $name
 * @property int $quota
 * @property int $sort_order
 */
#[UseFactory(AdmissionPathFactory::class)]
#[Fillable(['period_id', 'name', 'quota', 'sort_order'])]
class AdmissionPath extends Model
{
    /** @use HasFactory<AdmissionPathFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'ppdb_paths';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quota' => 'integer', 'sort_order' => 'integer'];
    }
}
