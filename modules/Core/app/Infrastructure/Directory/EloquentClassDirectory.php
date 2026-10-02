<?php

namespace Modules\Core\App\Infrastructure\Directory;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\App\Contracts\ClassDirectory;
use Modules\Core\App\Contracts\DTOs\ClassRecord;
use Modules\Core\App\Contracts\DTOs\ClassSubject;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Models\TeachingAssignment;

final class EloquentClassDirectory implements ClassDirectory
{
    public function ofActiveYear(): array
    {
        $yearId = $this->activeYearId();

        return $yearId === null ? [] : $this->ofYear($yearId);
    }

    public function ofYear(int $academicYearId): array
    {
        return array_values(
            $this->query()
                ->where('academic_year_id', $academicYearId)
                ->get()
                ->sort(fn (ClassGroup $a, ClassGroup $b): int => strnatcasecmp($a->name, $b->name))
                ->map($this->record(...))
                ->all(),
        );
    }

    public function find(int $id): ?ClassRecord
    {
        $class = $this->query()->find($id);

        return $class === null ? null : $this->record($class);
    }

    public function subjectsOf(int $classId): array
    {
        return array_values(
            TeachingAssignment::query()
                ->where('class_id', $classId)
                ->with(['subject', 'teacher'])
                ->get()
                ->filter(fn (TeachingAssignment $assignment): bool => $assignment->subject !== null && $assignment->teacher !== null)
                ->sortBy(fn (TeachingAssignment $assignment): string => mb_strtolower($assignment->subject->name))
                ->map(fn (TeachingAssignment $assignment): ClassSubject => new ClassSubject(
                    subjectId: $assignment->subject_id,
                    subjectName: $assignment->subject->name,
                    teacherId: $assignment->teacher_id,
                    teacherName: $assignment->teacher->name,
                    teacherUserId: $assignment->teacher->user_id,
                ))
                ->all(),
        );
    }

    public function idsTaughtBy(int $userId): array
    {
        $yearId = $this->activeYearId();
        $teacherId = Teacher::query()->where('user_id', $userId)->value('id');

        if ($yearId === null || $teacherId === null) {
            return [];
        }

        return array_values(
            ClassGroup::query()
                ->where('academic_year_id', $yearId)
                ->where(fn (Builder $query) => $query
                    ->where('homeroom_teacher_id', $teacherId)
                    ->orWhereHas('teachingAssignments', fn (Builder $assignments) => $assignments->where('teacher_id', $teacherId)))
                ->orderBy('id')
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all(),
        );
    }

    private function activeYearId(): ?int
    {
        return AcademicYear::query()->where('status', AcademicYearStatus::Active)->value('id');
    }

    /**
     * @return Builder<ClassGroup>
     */
    private function query(): Builder
    {
        return ClassGroup::query()
            ->with('homeroom')
            ->withCount(['students' => fn (Builder $students) => $students->where('status', 'active')]);
    }

    private function record(ClassGroup $class): ClassRecord
    {
        return new ClassRecord(
            id: $class->id,
            name: $class->name,
            academicYearId: $class->academic_year_id,
            homeroomName: $class->homeroom?->name,
            studentCount: (int) $class->getAttribute('students_count'),
        );
    }
}
