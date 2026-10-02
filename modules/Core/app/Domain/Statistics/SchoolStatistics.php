<?php

namespace Modules\Core\App\Domain\Statistics;

use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\DTOs\StatFigure;
use Modules\Core\App\Contracts\DTOs\StatPanel;
use Modules\Core\App\Contracts\StatisticsProvider;
use Modules\Core\App\Domain\Enums\SchoolLevel;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\SchoolProfile;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Teacher;

/**
 * Core's own figures on the Statistik page: how many active students,
 * teachers and classes the school has, and how the active students spread
 * over grades and gender. Classes and the spread by grade follow the
 * academic year asked for; the headcounts are the school as it is now.
 */
final class SchoolStatistics implements StatisticsProvider
{
    public function figures(?ReportPeriod $period): array
    {
        return [
            new StatFigure('students', 'Siswa aktif', Student::query()->where('status', 'active')->count()),
            new StatFigure('teachers', 'Guru & tendik', Teacher::query()->count()),
            new StatFigure(
                'classes',
                'Kelas',
                $period === null ? 0 : ClassGroup::query()->where('academic_year_id', $period->academicYearId)->count(),
                $period?->name,
            ),
        ];
    }

    public function panels(?ReportPeriod $period): array
    {
        return [$this->studentsByGrade($period), $this->studentsByGender()];
    }

    private function studentsByGrade(?ReportPeriod $period): StatPanel
    {
        $studentsOfGrade = $period === null ? collect() : ClassGroup::query()
            ->where('academic_year_id', $period->academicYearId)
            ->withCount(['students' => fn ($query) => $query->where('status', 'active')])
            ->get()
            ->groupBy('grade_id')
            ->map(fn ($classes): int => (int) $classes->sum('students_count'));

        $points = Grade::query()->orderBy('sort_order')->get()
            ->map(fn (Grade $grade): array => ['label' => $grade->name, 'value' => $studentsOfGrade->get($grade->id, 0)])
            ->all();

        return new StatPanel(
            key: 'students-by-grade',
            title: SchoolProfile::current()->level === SchoolLevel::Sd ? 'Siswa per kelas' : 'Siswa per tingkat',
            kind: StatPanel::KIND_BARS,
            points: array_values($points),
            note: $period === null ? null : "Siswa aktif pada rombel tahun ajaran {$period->name}.",
        );
    }

    private function studentsByGender(): StatPanel
    {
        $totals = Student::query()
            ->where('status', 'active')
            ->selectRaw('gender, count(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender');

        return new StatPanel(
            key: 'students-by-gender',
            title: 'Jenis kelamin',
            kind: StatPanel::KIND_SHARE,
            points: [
                ['label' => 'Laki-laki', 'value' => (int) $totals->get('L', 0)],
                ['label' => 'Perempuan', 'value' => (int) $totals->get('P', 0)],
            ],
        );
    }
}
