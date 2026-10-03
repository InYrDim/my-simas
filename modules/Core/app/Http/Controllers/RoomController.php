<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Modules\Core\App\Domain\Actions\DeleteRoom;
use Modules\Core\App\Domain\Actions\SaveRoom;
use Modules\Core\App\Domain\Models\Room;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Requests\RoomRequest;
use Modules\Core\App\Http\Resources\RoomResource;

final class RoomController
{
    use RendersMasterPage;

    public function index(): Response
    {
        return $this->renderMaster('Core/Master/Rooms/Index', [
            'rooms' => RoomResource::collection(Room::query()->orderBy('code')->get())->resolve(),
        ]);
    }

    public function store(RoomRequest $request, SaveRoom $save): RedirectResponse
    {
        $room = $save->handle(null, $request->roomData());

        return back()->with('status', "Ruangan {$room->name} ditambahkan.");
    }

    public function update(RoomRequest $request, Room $room, SaveRoom $save): RedirectResponse
    {
        $save->handle($room, $request->roomData());

        return back()->with('status', "Ruangan {$room->name} diperbarui.");
    }

    public function destroy(Room $room, DeleteRoom $delete): RedirectResponse
    {
        $delete->handle($room);

        return back()->with('status', "Ruangan {$room->name} dihapus.");
    }
}
