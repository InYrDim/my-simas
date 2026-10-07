<?php

namespace Modules\Core\App\Domain\Dashboard;

use Illuminate\Support\Facades\Auth;
use Modules\Core\App\Contracts\ClassTimetable;
use Modules\Core\App\Contracts\DashboardWidgetProvider;
use Modules\Core\App\Contracts\DTOs\DashboardWidget;
use Modules\Core\App\Contracts\DTOs\ScheduleLesson;
use Modules\Core\App\Domain\Models\Student;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Core's own Beranda blocks, registered through the same contract the
 * feature modules use. What is only a count or a link of Core's own
 * (accounts, setup, a teacher's classes, shortcuts) still travels as
 * plain page props; this holds what is worth waiting for.
 *
 * Today that is the student's lessons for the day, read from the class
 * timetable on the school's own clock.
 */
final class SchoolDashboard implements DashboardWidgetProvider
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ClassTimetable $timetable,
    ) {}

    /**
     * @return list<DashboardWidget>
     */
    public function widgets(): array
    {
        return array_values(array_filter([$this->studentLessonsToday()]));
    }

    private function studentLessonsToday(): ?DashboardWidget
    {
        $userId = Auth::id();

        $student = $userId === null ? null : Student::query()->where('user_id', $userId)->first();

        if ($student === null || $student->class_id === null) {
            return null;
        }

        $weekday = now($this->context->currentOrFail()->timezone)->dayOfWeekIso;

        $lessons = [];

        foreach ($this->timetable->week($student->class_id) as $day) {
            if ($day->day === $weekday) {
                $lessons = $day->lessons;
            }
        }

        return new DashboardWidget(
            key: 'core.student-lessons-today',
            kind: DashboardWidget::KIND_LIST,
            slot: DashboardWidget::SLOT_MAIN,
            title: 'Jadwal hari ini',
            payload: [
                'items' => array_map(fn (ScheduleLesson $lesson): array => [
                    'label' => $lesson->subjectName,
                    'detail' => "Jam ke-{$lesson->order} · {$lesson->startsAt}–{$lesson->endsAt}".($lesson->teacherName === null ? '' : " · {$lesson->teacherName}"),
                ], $lessons),
                'empty' => 'Tidak ada pelajaran hari ini.',
            ],
            order: 30,
            permission: 'core.me.view',
        );
    }
}
