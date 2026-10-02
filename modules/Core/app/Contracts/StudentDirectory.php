<?php

namespace Modules\Core\App\Contracts;

use Modules\Core\App\Contracts\DTOs\StudentRecord;

/**
 * Read access to the current school's students for other modules. A
 * student of another school is never found.
 */
interface StudentDirectory
{
    public function find(int $id): ?StudentRecord;

    /**
     * The student whose record carries this login account.
     */
    public function findByUserId(int $userId): ?StudentRecord;

    /**
     * @param  list<int>  $ids
     * @return array<int, StudentRecord> keyed by student id
     */
    public function many(array $ids): array;

    /**
     * The active students of a class, by name.
     *
     * @return list<StudentRecord>
     */
    public function ofClass(int $classId): array;

    /**
     * Active students whose name or NIS contains the term, by name.
     *
     * @return list<StudentRecord>
     */
    public function search(string $term, int $limit = 10): array;
}
