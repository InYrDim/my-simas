<?php

namespace Modules\Core\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\App\Domain\Models\PeriodSlot;

/**
 * @mixin PeriodSlot
 */
final class PeriodSlotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start' => substr($this->start_time, 0, 5),
            'end' => substr($this->end_time, 0, 5),
            'type' => $this->type,
        ];
    }
}
