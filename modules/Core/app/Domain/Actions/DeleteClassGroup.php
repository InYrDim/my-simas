<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\ClassGroup;

final class DeleteClassGroup
{
    /**
     * @throws ValidationException when students are still placed in the class
     */
    public function handle(ClassGroup $class): void
    {
        if ($class->students()->exists()) {
            throw ValidationException::withMessages([
                'status' => "Kelas {$class->name} masih memiliki siswa. Pindahkan siswanya lebih dulu.",
            ]);
        }

        $class->delete();
    }
}
