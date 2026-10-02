<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * A class group (rombel) of one academic year, with the number of active
 * students in it.
 */
final readonly class ClassRecord
{
    public function __construct(
        public int $id,
        public string $name,
        public int $academicYearId,
        public ?string $homeroomName,
        public int $studentCount,
    ) {}
}
