<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\SchoolProfile;

final class SaveSchoolProfile
{
    public function __construct(private readonly SyncDefaultGrades $syncGrades) {}

    /**
     * The jenjang is locked once the school has a class: its grades and
     * majors hang on it.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when the jenjang changes while classes exist
     */
    public function handle(array $data): SchoolProfile
    {
        $profile = SchoolProfile::current();
        $profile->fill($data);

        if ($profile->isDirty('level') && $this->isLevelLocked()) {
            throw ValidationException::withMessages([
                'level' => 'Jenjang tidak bisa diubah karena sekolah sudah memiliki kelas.',
            ]);
        }

        $profile->save();

        $this->syncGrades->handle($profile);

        return $profile;
    }

    public function isLevelLocked(): bool
    {
        return ClassGroup::query()->exists();
    }
}
