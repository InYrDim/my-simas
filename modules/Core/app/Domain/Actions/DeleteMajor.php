<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\Major;

final class DeleteMajor
{
    /**
     * @throws ValidationException when a class still belongs to the major
     */
    public function handle(Major $major): void
    {
        if ($major->classes()->exists()) {
            throw ValidationException::withMessages([
                'status' => "Jurusan {$major->code} masih dipakai oleh kelas dan tidak bisa dihapus.",
            ]);
        }

        $major->delete();
    }
}
