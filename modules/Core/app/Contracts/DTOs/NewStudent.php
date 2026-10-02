<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * What a module hands to Core to have a student admitted: the person, as
 * far as the school's records need it. Core decides the rest (status,
 * class, login account).
 */
final readonly class NewStudent
{
    /**
     * @param  string  $gender  `L` or `P`
     * @param  string|null  $birthDate  `Y-m-d`
     */
    public function __construct(
        public string $name,
        public string $nis,
        public string $gender,
        public ?string $nisn = null,
        public ?string $birthDate = null,
        public ?string $guardianName = null,
        public ?string $guardianPhone = null,
    ) {}
}
