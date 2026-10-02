<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\App\Domain\Enums\ImportMode;
use Modules\Core\App\Domain\Models\Student;

/**
 * Imports the rows of a student CSV: rows that fail the check are
 * skipped, the others are saved through SaveStudent (which keeps the
 * class history right) in one transaction.
 */
final class ImportStudents
{
    public function __construct(
        private readonly CheckStudentImport $check,
        private readonly SaveStudent $save,
    ) {}

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function handle(array $rows, ImportMode $mode): array
    {
        $checked = $this->check->handle($rows, $mode);

        return DB::transaction(function () use ($checked): array {
            $existing = Student::query()->findMany(array_filter(array_column($checked, 'id')))->keyBy('id');
            $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

            foreach ($checked as $row) {
                if ($row['outcome'] === 'error') {
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
