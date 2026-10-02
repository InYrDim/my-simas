<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * One headline number on the Statistik page (e.g. "Siswa aktif: 612").
 */
final readonly class StatFigure
{
    public function __construct(
        public string $key,
        public string $label,
        public int|float|string $value,
        public ?string $hint = null,
    ) {}
}
