<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Modules\Core\App\Domain\Actions\CopyPeriodDay;
use Modules\Core\App\Domain\Actions\SavePeriodSlot;
use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Requests\CopyPeriodDayRequest;
use Modules\Core\App\Http\Requests\PeriodSlotRequest;
use Modules\Core\App\Http\Resources\PeriodSlotResource;

final class PeriodSlotController
{
    use RendersMasterPage;

    public function index(): Response
    {
        $slots = PeriodSlot::query()->orderBy('day')->orderBy('start_time')->get()->groupBy('day');

        $days = [];

        foreach (PeriodSlot::DAYS as $number => $name) {
            $lesson = 0;
            $rows = [];

            foreach ($slots->get($number, collect()) as $slot) {
                $rows[] = [
                    ...PeriodSlotResource::make($slot)->resolve(),
                    'order' => $slot->type === PeriodSlot::LESSON ? ++$lesson : null,
                ];
            }

            $days[] = ['day' => $name, 'dayNumber' => $number, 'slots' => $rows];
        }

        return $this->renderMaster('Core/Academic/Periods/Index', [
            'days' => $days,
            'types' => PeriodSlot::TYPES,
        ]);
    }

    public function store(PeriodSlotRequest $request, SavePeriodSlot $save): RedirectResponse
    {
        $slot = $save->handle(null, $request->slotData());

        return back()->with('status', 'Jam '.PeriodSlot::DAYS[$slot->day].' ditambahkan.');
    }

    public function update(PeriodSlotRequest $request, PeriodSlot $periodSlot, SavePeriodSlot $save): RedirectResponse
    {
        $save->handle($periodSlot, $request->slotData());

        return back()->with('status', 'Jam diperbarui.');
    }

    public function destroy(PeriodSlot $periodSlot): RedirectResponse
    {
        $periodSlot->delete();

        return back()->with('status', 'Jam dihapus.');
    }

    public function copy(CopyPeriodDayRequest $request, CopyPeriodDay $copy): RedirectResponse
    {
        $copy->handle($request->integer('from_day'), $request->targetDays());

        return back()->with('status', 'Jam pelajaran '.PeriodSlot::DAYS[$request->integer('from_day')].' disalin.');
    }
}
