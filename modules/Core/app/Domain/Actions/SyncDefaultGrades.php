<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\App\Domain\Enums\SchoolLevel;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\SchoolProfile;

/**
 * Keeps the grade levels (tingkat) in line with the school's jenjang:
 * SD 1–6, SMP 7–9, SMA/SMK X–XII. Grades are seeded when missing and
 * re-seeded when the jenjang changes — but never once classes exist,
 * since they hang on those grades.
 */
final class SyncDefaultGrades
{
    public function handle(SchoolProfile $profile): void
    {
        $wanted = $this->namesFor($profile->level);
        $current = Grade::query()->orderBy('sort_order')->pluck('name')->all();

        if ($current === $wanted || ($current !== [] && ClassGroup::query()->exists())) {
            return;
        }

        DB::transaction(function () use ($wanted): void {
            Grade::query()->delete();

            foreach ($wanted as $index => $name) {
                Grade::query()->create(['name' => $name, 'sort_order' => $index + 1]);
            }
        });
    }

    /**
     * @return list<string>
     */
    private function namesFor(SchoolLevel $level): array
    {
        return match ($level) {
            SchoolLevel::Sd => ['1', '2', '3', '4', '5', '6'],
            SchoolLevel::Smp => ['7', '8', '9'],
            SchoolLevel::Sma, SchoolLevel::Smk => ['X', 'XI', 'XII'],
        };
    }
}
