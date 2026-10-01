<?php

namespace Modules\Core\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\App\Domain\Models\AcademicYear;

/**
 * @mixin AcademicYear
 */
final class AcademicYearResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'curriculum' => $this->curriculum,
            'start' => $this->start_date->toDateString(),
            'end' => $this->end_date->toDateString(),
            'status' => $this->status->value,
            'semesters' => $this->semesters->map(fn ($semester): array => [
                'name' => $semester->name,
                'start' => $semester->start_date->toDateString(),
                'end' => $semester->end_date->toDateString(),
            ])->all(),
        ];
    }
}
