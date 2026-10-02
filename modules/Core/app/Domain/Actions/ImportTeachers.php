<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\App\Domain\Enums\ImportMode;
use Modules\Core\App\Domain\Models\Teacher;

/**
 * Imports the rows of a teacher CSV: rows that fail the check are
 * skipped, the others are saved through SaveTeacher in one transaction.
 */
final class ImportTeachers
{
    public function __construct(
        private readonly CheckTeacherImport $check,
        private readonly SaveTeacher $save,
    ) {}

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function handle(array $rows, ImportMode $mode): array
    {
        $checked = $this->check->handle($rows, $mode);

        return DB::transaction(function () use ($checked): array {
            $existing = Teacher::query()->findMany(array_filter(array_column($checked, 'id')))->keyBy('id');
            $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

            foreach ($checked as $row) {
                if ($row['data'] === null) {
                    $counts['skipped']++;

                    continue;
                }

                $this->save->handle($row['id'] === null ? null : $existing->get($row['id']), $row['data']);
                $counts[$row['outcome'] === 'update' ? 'updated' : 'created']++;
            }

            return $counts;
        });
    }
}
