<?php

namespace Modules\Core\App\Infrastructure\Directory;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\App\Contracts\DTOs\StudentRecord;
use Modules\Core\App\Contracts\StudentDirectory;
use Modules\Core\App\Domain\Models\Student;

final class EloquentStudentDirectory implements StudentDirectory
{
    public function find(int $id): ?StudentRecord
    {
        $student = Student::query()->with('classGroup')->find($id);

        return $student === null ? null : $this->record($student);
    }

    public function findByUserId(int $userId): ?StudentRecord
    {
        $student = Student::query()->with('classGroup')->where('user_id', $userId)->first();

        return $student === null ? null : $this->record($student);
    }

    public function many(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Student::query()
            ->with('classGroup')
            ->whereKey($ids)
            ->get()
            ->mapWithKeys(fn (Student $student): array => [$student->id => $this->record($student)])
            ->all();
    }

    public function ofClass(int $classId): array
    {
        return $this->records(
            Student::query()->where('class_id', $classId)->where('status', 'active'),
        );
    }

    public function search(string $term, int $limit = 10): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $this->records(
            Student::query()
                ->where('status', 'active')
                ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('nis', 'like', $like))
                ->limit($limit),
        );
    }

    /**
     * @param  Builder<Student>  $query
     * @return list<StudentRecord>
     */
    private function records(Builder $query): array
    {
        return array_values(
            $query->with('classGroup')->orderBy('name')->get()->map($this->record(...))->all(),
        );
    }

    private function record(Student $student): StudentRecord
    {
        return new StudentRecord(
            id: $student->id,
            name: $student->name,
            nis: $student->nis,
            classId: $student->class_id,
            className: $student->classGroup?->name,
            active: $student->isActive(),
        );
    }
}
