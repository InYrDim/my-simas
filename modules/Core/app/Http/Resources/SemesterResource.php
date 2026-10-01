<?php

namespace Modules\Core\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\App\Domain\Models\Semester;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * One row of the Semester page. Status and week are derived from the
 * school's own clock.
 *
 * @mixin Semester
 */
final class SemesterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $today = now(app(TenantContext::class)->currentOrFail()->timezone);
        $status = $this->statusOn($today);

        return [
            'id' => $this->id,
            'year' => $this->academicYear->name,
            'name' => $this->name,
            'start' => $this->start_date->toDateString(),
            'end' => $this->end_date->toDateString(),
            'weeks' => $this->weeks(),
            'status' => $status,
            'week' => $status === 'current' ? $this->weekOn($today) : null,
        ];
    }
}
