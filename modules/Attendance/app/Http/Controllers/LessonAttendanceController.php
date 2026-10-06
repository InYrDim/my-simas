<?php

namespace Modules\Attendance\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Domain\Actions\SaveLessonAttendance;
use Modules\Attendance\App\Domain\Models\LessonSession;
use Modules\Attendance\App\Domain\Queries\ClassChoices;
use Modules\Attendance\App\Domain\Queries\LessonRoll;
use Modules\Attendance\App\Domain\Support\LessonSlots;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Attendance\App\Http\Concerns\KnowsSignedInUser;
use Modules\Attendance\App\Http\Concerns\ReadsAttendanceFilters;
use Modules\Attendance\App\Http\Requests\LessonAttendanceRequest;
use Modules\Core\App\Contracts\ClassDirectory;
use Modules\Core\App\Contracts\DTOs\BellSlot;
use Modules\Core\App\Contracts\DTOs\ClassSubject;

/**
 * Absensi Jam Pelajaran: one class in one lesson slot of one day.
 */
final class LessonAttendanceController
{
    use KnowsSignedInUser, ReadsAttendanceFilters;

    public function index(
        Request $request,
        ClassChoices $choices,
        ClassDirectory $directory,
        LessonRoll $roll,
        LessonSlots $lessonSlots,
        SchoolClock $clock,
    ): Response {
        $userId = $this->signedInUserId();
        $date = $this->requestedDate($request, $clock);
        $classes = $choices->for($userId);
        $classId = $choices->pick($classes, $this->requestedClass($request));

        $slots = $lessonSlots->on($date);
        $slotId = $this->pickSlot($slots, $request->query('jam'), $date, $clock);

        $session = $classId === null || $slotId === null ? null : LessonSession::query()
            ->where('class_id', $classId)
            ->where('date', $date)
            ->where('period_slot_id', $slotId)
            ->first();

        $subjects = $classId === null ? [] : $directory->subjectsOf($classId);
        $subjectId = $session !== null
            ? $session->subject_id
            : ($classId === null ? null : $choices->ownSubject($classId, $userId));

        return Inertia::render('Attendance/Lessons', [
            'date' => $this->dateProp($date, $clock),
            'today' => $clock->today(),
            'classes' => $classes,
            'classId' => $classId === null ? '' : (string) $classId,
            'slots' => $this->slotOptions($slots),
            'slotId' => $slotId === null ? '' : (string) $slotId,
            'subjects' => array_map(fn (ClassSubject $subject): array => [
                'value' => (string) $subject->subjectId,
                'label' => "{$subject->subjectName} — {$subject->teacherName}",
            ], $subjects),
            'subjectId' => $subjectId === null ? '' : (string) $subjectId,
            'recorded' => $session !== null,
            'students' => $classId === null ? [] : $roll->forClass($classId, $date, $session),
        ]);
    }

    public function update(LessonAttendanceRequest $request, SaveLessonAttendance $save): RedirectResponse
    {
        $save->handle(
            (int) $request->validated('class_id'),
            (string) $request->validated('date'),
            (int) $request->validated('period_slot_id'),
            $request->subjectId(),
            $request->marks(),
            $this->signedInUserId(),
        );

        return back()->with('status', 'Absensi jam pelajaran disimpan.');
    }

    /**
     * The slot to open: the one asked for, else the lesson running now
     * (today only), else the day's first.
     *
     * @param  list<BellSlot>  $slots
     */
    private function pickSlot(array $slots, mixed $requested, string $date, SchoolClock $clock): ?int
    {
        $ids = array_column($slots, 'id');

        if (is_string($requested) && in_array((int) $requested, $ids, true)) {
            return (int) $requested;
        }

        if ($date === $clock->today()) {
            $now = $clock->now()->format('H:i');

            foreach ($slots as $slot) {
                if ($slot->startsAt <= $now && $now < $slot->endsAt) {
                    return $slot->id;
                }
            }
        }

        return $ids[0] ?? null;
    }

    /**
     * @param  list<BellSlot>  $slots
     * @return list<array{value: string, label: string}>
     */
    private function slotOptions(array $slots): array
    {
        $options = [];

        foreach ($slots as $index => $slot) {
            $options[] = [
                'value' => (string) $slot->id,
                'label' => 'Jam ke-'.($index + 1)." · {$slot->startsAt}–{$slot->endsAt}",
            ];
        }

        return $options;
    }
}
