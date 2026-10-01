<?php

namespace Modules\Core\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\App\Domain\Models\Grade;

/**
 * `classes_count` is the number of classes of the active academic year,
 * loaded by the controller.
 *
 * @mixin Grade
 */
final class GradeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'order' => $this->sort_order,
            'classes' => (int) ($this->classes_count ?? 0),
        ];
    }
}
