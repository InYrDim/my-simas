<?php

namespace Modules\Attendance\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Enums\RecordMethod;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Models\LessonAttendance;
use Modules\Attendance\App\Domain\Models\LessonSession;
use Modules\Attendance\App\Domain\Notifications\AttendanceNotices;
use Modules\Attendance\App\Domain\Support\LessonSlots;
use Modules\Core\App\Contracts\ClassDirectory;
use Modules\Core\App\Contracts\DTOs\ClassSubject;
use Modules\Core\App\Contracts\StudentDirectory;

final class SaveLessonAttendance
{
    public function __construct(
        private readonly LessonSlots $slots,
        private readonly ClassDirectory $classes,
        private readonly StudentDirectory $students,
        private readonly AttendanceNotices $notices,
    ) {}

    /**
     * The attendance of one class in one lesson slot of one day. There is
     * one session per class, day and slot: saving again updates it. The
     * subject is optional and must be one taught in that class.
     *
     * A guardian hears about an absence only when the day is today, the
     * student just became absent in this lesson, and the day's own record
     * does not already say sick, excused or absent.
     *
     * @param  string  $date  Y-m-d
     * @param  array<int, AttendanceStatus>  $marks  keyed by student id
     *
     * @throws ValidationException
     */
    public function handle(int $classId, string $date, int $slotId, ?int $subjectId, array $marks, ?int $recordedBy): LessonSession
    {
        [, $slot] = $this->slots->resolve($classId, $date, $slotId);

        $subject = null;

        if ($subjectId !== null) {
            $subject = collect($this->classes->subjectsOf($classId))
                ->first(fn (ClassSubject $candidate): bool => $candidate->subjectId === $subjectId);

            if ($subject === null) {
                throw ValidationException::withMessages(['subject_id' => 'Mata pelajaran ini tidak diajarkan di kelas tersebut.']);
            }
        }

        $members = array_column($this->students->ofClass($classId), 'id');

        if (array_diff(array_keys($marks), $members) !== []) {
            throw ValidationException::withMessages(['marks' => 'Ada siswa yang bukan anggota kelas ini.']);
        }

        foreach ($marks as $status) {
            if (! in_array($status, AttendanceStatus::forLessons(), true)) {
                throw ValidationException::withMessages(['marks' => 'Status terlambat hanya berlaku untuk absensi gerbang.']);
            }
        }

        /** @var array{0: LessonSession, 1: list<int>} $result */
        $result = DB::transaction(function () use ($classId, $date, $slot, $subject, $marks, $recordedBy): array {
            $session = LessonSession::query()->firstOrNew([
                'class_id' => $classId,
                'date' => $date,
                'period_slot_id' => $slot->id,
            ]);
            $session->fill([
                'start_time' => "{$slot->startsAt}:00",
                'end_time' => "{$slot->endsAt}:00",
                'subject_id' => $subject?->subjectId,
                'teacher_id' => $subject?->teacherId,
                'recorded_by' => $recordedBy,
            ])->save();

            $existing = $session->attendances()->get()->keyBy('student_id');
            $newlyAbsent = [];

            foreach ($marks as $studentId => $status) {
                /** @var LessonAttendance|null $row */
                $row = $existing->get($studentId);

                if ($status === AttendanceStatus::Absent && $row?->status !== AttendanceStatus::Absent) {
                    $newlyAbsent[] = $studentId;
                }

                // A scan stays a scan while the student stays present.
                if ($row !== null && $row->status === $status) {
                    continue;
                }

                $row ??= new LessonAttendance(['lesson_session_id' => $session->id, 'student_id' => $studentId]);
                $row->fill(['status' => $status, 'method' => RecordMethod::Manual, 'scanned_at' => null])->save();
            }

            return [$session, $newlyAbsent];
        });

        [$session, $newlyAbsent] = $result;

        if ($newlyAbsent !== []) {
            $awayToday = DailyAttendance::query()
                ->where('date', $date)
                ->whereIn('student_id', $newlyAbsent)
                ->whereIn('status', [AttendanceStatus::Sick, AttendanceStatus::Permit, AttendanceStatus::Absent])
                ->pluck('student_id')
                ->all();

            foreach (array_diff($newlyAbsent, $awayToday) as $studentId) {
                $this->notices->lessonAbsence($studentId, $date, $slot->startsAt, $slot->endsAt, $subject?->subjectName);
            }
        }

        return $session;
    }
}
