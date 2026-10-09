<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * An action another module offers on the Siswa list: one link for the
 * whole list and one per student. `studentUrl` ends where the student's id
 * goes: the page appends the id to it. Both open in a new tab.
 */
final readonly class StudentAction
{
    public function __construct(
        public string $key,
        public string $label,
        public string $allUrl,
        public string $studentUrl,
    ) {}
}
