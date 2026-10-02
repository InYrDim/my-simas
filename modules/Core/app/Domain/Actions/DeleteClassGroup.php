<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\ClassGroup;

final class DeleteClassGroup
{
    /**
     * Teaching assignments of the class go with it.
     *
     * @throws ValidationException when students are still placed in the class
     */
    public function handle(ClassGroup $class): void
    {
        if ($class->students()->exists()) {
            throw ValidationException::withMessages([
                'status' => "Kelas {$class->name} masih memiliki siswa. Pindahkan siswanya lebih dulu.",
            ]);
        }

        DB::transaction(function () use ($class): void {
            $class->teachingAssignments()->delete();
            $class->delete();
        });
    }
}
