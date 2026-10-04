<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Core\App\Domain\Models\TimetableEntry;

/**
 * Replaces the slots of the target weekdays with a copy of the source
 * weekday's slots.
 */
final class CopyPeriodDay
{
    /**
     * @param  list<int>  $toDays
     *
     * @throws ValidationException when the source day has no slots or is also a target
     */
    public function handle(int $fromDay, array $toDays): void
    {
        $slots = PeriodSlot::query()->where('day', $fromDay)->orderBy('start_time')->get();

        if ($slots->isEmpty()) {
            throw ValidationException::withMessages(['from_day' => 'Hari asal belum memiliki jam untuk disalin.']);
        }

        if (in_array($fromDay, $toDays, true)) {
            throw ValidationException::withMessages(['days' => 'Hari tujuan tidak boleh sama dengan hari asal.']);
        }

        DB::transaction(function () use ($slots, $toDays): void {
            $replaced = PeriodSlot::query()->whereIn('day', $toDays)->pluck('id');

            TimetableEntry::query()->whereIn('period_slot_id', $replaced)->delete();
            PeriodSlot::query()->whereIn('day', $toDays)->delete();

            foreach ($toDays as $day) {
                foreach ($slots as $slot) {
                    PeriodSlot::query()->create([
                        'day' => $day,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'type' => $slot->type,
                    ]);
                }
            }
        });
    }
}
