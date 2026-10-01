<?php

namespace Modules\Core\App\Domain\Actions;

use Modules\Core\App\Domain\Models\Room;

final class SaveRoom
{
    /**
     * @param  array{code: string, name: string, type: string, capacity: int, status: string}  $data
     */
    public function handle(?Room $room, array $data): Room
    {
        $room ??= new Room;
        $room->fill($data)->save();

        return $room;
    }
}
