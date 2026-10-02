<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Modules\Core\App\Domain\Actions\SaveCalendarEvent;
use Modules\Core\App\Domain\Models\CalendarEvent;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Requests\CalendarEventRequest;
use Modules\Core\App\Http\Resources\CalendarEventResource;
use Modules\Platform\App\Contracts\TenantContext;

final class CalendarEventController
{
    use RendersMasterPage;

    public function index(TenantContext $context): Response
    {
        $events = CalendarEvent::query()->orderBy('start_date')->orderBy('id')->get();

        return $this->renderMaster('Core/Academic/Calendar/Index', [
            'events' => CalendarEventResource::collection($events)->resolve(),
            'today' => now($context->currentOrFail()->timezone)->toDateString(),
        ]);
    }

    public function store(CalendarEventRequest $request, SaveCalendarEvent $save): RedirectResponse
    {
        $event = $save->handle(null, $request->eventData());

        return back()->with('status', "Peristiwa {$event->title} ditambahkan.");
    }

    public function update(CalendarEventRequest $request, CalendarEvent $calendarEvent, SaveCalendarEvent $save): RedirectResponse
    {
        $save->handle($calendarEvent, $request->eventData());

        return back()->with('status', "Peristiwa {$calendarEvent->title} diperbarui.");
    }

    public function destroy(CalendarEvent $calendarEvent): RedirectResponse
    {
        $calendarEvent->delete();

        return back()->with('status', "Peristiwa {$calendarEvent->title} dihapus.");
    }
}
