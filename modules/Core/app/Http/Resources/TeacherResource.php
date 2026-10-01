<?php

namespace Modules\Core\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\App\Domain\Models\Teacher;

/**
 * @mixin Teacher
 */
final class TeacherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nip' => $this->nip,
            'nuptk' => $this->nuptk,
            'employment' => $this->employment,
            'duty' => $this->duty,
            'hasAccount' => $this->user_id !== null,
            'email' => $this->email,
        ];
    }
}
