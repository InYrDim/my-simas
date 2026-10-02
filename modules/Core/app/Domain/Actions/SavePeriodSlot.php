<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\PeriodSlot;

final class SavePeriodSlot
{
    /**
     * A slot ends after it starts and never overlaps another slot of the
     * same weekday.
     *
     * @param  array{day: int, start_time: string, end_time: string, type: string}  $data
     *
     * @throws ValidationException
     */
    public function handle(?PeriodSlot $slot, array $data): PeriodSlot
    {
        $data['start_time'] = Carbon::parse($data['start_time'])->format('H:i:s');
        $data['end_time'] = Carbon::parse($data['end_time'])->format('H:i:s');

        if ($data['end_time'] <= $data['start_time']) {
            throw ValidationException::withMessages(['end_time' => 'Jam selesai harus setelah jam mulai.']);
        }

        $overlaps = PeriodSlot::query()
            ->where('day', $data['day'])
            ->when($slot?->exists, fn ($query) => $query->whereKeyNot($slot->id))
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['start_time' => 'Jam ini bertabrakan dengan jam lain di hari yang sama.']);
        }

        $slot ??= new PeriodSlot;
        $slot->fill($data)->save();

        return $slot;
    }
}
