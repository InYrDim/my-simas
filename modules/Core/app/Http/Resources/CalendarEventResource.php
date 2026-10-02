<?php

namespace Modules\Core\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\App\Domain\Models\CalendarEvent;

/**
 * @mixin CalendarEvent
 */
final class CalendarEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->start_date->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
            'title' => $this->title,
            'category' => $this->category,
        ];
    }
}
