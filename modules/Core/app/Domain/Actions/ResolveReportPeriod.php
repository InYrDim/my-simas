<?php

namespace Modules\Core\App\Domain\Actions;

use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\AcademicYear;

/**
 * The academic year Statistik & Laporan work on: the one asked for when
 * it belongs to the school, otherwise the active year, otherwise the
 * latest one. Null for a school that has no academic year yet.
 */
final class ResolveReportPeriod
{
    public function handle(?int $academicYearId = null): ?ReportPeriod
    {
        $year = ($academicYearId === null ? null : AcademicYear::query()->find($academicYearId))
            ?? AcademicYear::query()->where('status', AcademicYearStatus::Active->value)->first()
            ?? AcademicYear::query()->orderByDesc('start_date')->first();

        if ($year === null) {
            return null;
        }

        return new ReportPeriod(
            academicYearId: $year->id,
            name: $year->name,
            startsOn: $year->start_date->toDateString(),
            endsOn: $year->end_date->toDateString(),
        );
    }
}
