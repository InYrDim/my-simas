<?php

namespace Modules\Core\App\Domain\Reports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Core\App\Contracts\DTOs\ReportDefinition;
use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\DTOs\ReportTable;
use Modules\Core\App\Contracts\Report;
use Modules\Core\App\Domain\Models\Teacher;

/**
 * Beban Mengajar Guru: one row per teacher who teaches in a class of the
 * academic year — how many classes and subjects, and the hours per week
 * in total. Staff without a teaching assignment are left out.
 */
final class TeachingLoadReport implements Report
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'teaching-load',
            group: 'Akademik',
            name: 'Beban Mengajar Guru',
            description: 'Jumlah rombel, mata pelajaran, dan jam mengajar per minggu setiap guru.',
            permission: 'core.academic.view',
            order: 30,
        );
    }

    public function table(ReportPeriod $period): ReportTable
    {
        $ofPeriod = fn (Builder $classes) => $classes->where('academic_year_id', $period->academicYearId);

        $rows = Teacher::query()
            ->whereHas('teachingAssignments.classGroup', $ofPeriod)
            ->with(['teachingAssignments' => fn (Relation $assignments) => $assignments->whereHas('classGroup', $ofPeriod)])
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Teacher $teacher): array => [
                $teacher->name,
                $teacher->nip,
                $teacher->duty,
                $teacher->teachingAssignments->unique('class_id')->count(),
                $teacher->teachingAssignments->unique('subject_id')->count(),
                (int) $teacher->teachingAssignments->sum('hours_per_week'),
            ])
            ->all();

        return new ReportTable(
            'Beban Mengajar Guru',
            ['Nama', 'NIP', 'Tugas', 'Jumlah rombel', 'Jumlah mapel', 'Jam per minggu'],
            array_values($rows),
        );
    }
}
