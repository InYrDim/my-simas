<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\CalendarEvent;

final class SaveCalendarEvent
{
    /**
     * A range never ends before it starts; a range of one day is stored
     * as a single-day event.
     *
     * @param  array{title: string, category: string, start_date: string, end_date?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function handle(?CalendarEvent $event, array $data): CalendarEvent
    {
        $end = $data['end_date'] ?? null;

        if ($end !== null && $end < $data['start_date']) {
            throw ValidationException::withMessages(['end_date' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.']);
        }

        $data['end_date'] = $end === $data['start_date'] ? null : $end;

        $event ??= new CalendarEvent;
        $event->fill($data)->save();

        return $event;
    }
}
