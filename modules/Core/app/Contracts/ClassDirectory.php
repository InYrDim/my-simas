<?php

namespace Modules\Core\App\Contracts;

use Modules\Core\App\Contracts\DTOs\ClassRecord;
use Modules\Core\App\Contracts\DTOs\ClassSubject;

/**
 * Read access to the current school's classes for other modules.
 */
interface ClassDirectory
{
    /**
     * The classes of the active academic year, by name. Empty for a school
     * without one.
     *
     * @return list<ClassRecord>
     */
    public function ofActiveYear(): array;

    /**
     * @return list<ClassRecord>
     */
    public function ofYear(int $academicYearId): array;

    public function find(int $id): ?ClassRecord;

    /**
     * The subjects taught in a class, by subject name.
     *
     * @return list<ClassSubject>
     */
    public function subjectsOf(int $classId): array;

    /**
     * Classes of the active academic year in which the teacher holding
     * this login account teaches a subject or is the homeroom teacher.
     * Empty when the account belongs to no teacher.
     *
     * @return list<int>
     */
    public function idsTaughtBy(int $userId): array;
}
