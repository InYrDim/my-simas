<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * What another module may know about a student: who it is and where it
 * sits. Guardian details stay in Core.
 */
final readonly class StudentRecord
{
    public function __construct(
        public int $id,
        public string $name,
        public string $nis,
        public ?int $classId,
        public ?string $className,
        public bool $active,
    ) {}
}
