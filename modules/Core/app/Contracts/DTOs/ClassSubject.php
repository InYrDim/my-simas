<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * A subject taught in a class and the teacher assigned to it.
 * `teacherUserId` is the teacher's login account, when there is one.
 */
final readonly class ClassSubject
{
    public function __construct(
        public int $subjectId,
        public string $subjectName,
        public int $teacherId,
        public string $teacherName,
        public ?int $teacherUserId,
    ) {}
}
