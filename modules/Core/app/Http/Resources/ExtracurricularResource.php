<?php

namespace Modules\Core\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\App\Domain\Models\Extracurricular;

/**
 * Needs `coach` loaded and `memberships_count` counted.
 *
 * @mixin Extracurricular
 */
final class ExtracurricularResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'schedule' => $this->schedule,
            'kind' => $this->kind,
            'coach' => $this->coach?->name,
            'coachId' => $this->coach_teacher_id,
            'members' => (int) ($this->memberships_count ?? 0),
        ];
    }
}
