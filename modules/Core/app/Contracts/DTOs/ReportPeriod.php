<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * The academic year a report or a set of figures is asked for. A module
 * filters its own data with the id or the dates; the academic year model
 * itself never leaves Core.
 */
final readonly class ReportPeriod
{
    /**
     * @param  string  $startsOn  Y-m-d
     * @param  string  $endsOn  Y-m-d
     */
    public function __construct(
        public int $academicYearId,
        public string $name,
        public string $startsOn,
        public string $endsOn,
    ) {}
}
