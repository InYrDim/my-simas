<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\Room;

final class DeleteRoom
{
    /**
     * @throws ValidationException when a class still uses the room
     */
    public function handle(Room $room): void
    {
        if ($room->classes()->exists()) {
            throw ValidationException::withMessages([
                'status' => "Ruangan {$room->name} masih dipakai oleh kelas dan tidak bisa dihapus.",
            ]);
        }

        $room->delete();
    }
}
