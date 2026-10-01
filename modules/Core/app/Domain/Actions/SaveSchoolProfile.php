<?php

namespace Modules\Core\App\Domain\Actions;

use Modules\Core\App\Domain\Models\SchoolProfile;

final class SaveSchoolProfile
{
    public function __construct(private readonly SyncDefaultGrades $syncGrades) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): SchoolProfile
    {
        $profile = SchoolProfile::current();
        $profile->fill($data)->save();

        $this->syncGrades->handle($profile);

        return $profile;
    }
}
