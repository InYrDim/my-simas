<?php

namespace Modules\Attendance\App\Http\Controllers;

use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Domain\Actions\MarkLessonPresence;
use Modules\Attendance\App\Domain\Actions\RecordGateCheckIn;
use Modules\Attendance\App\Domain\Actions\RecordGateCheckOut;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Enums\LessonState;
use Modules\Attendance\App\Domain\Enums\RecordMethod;
use Modules\Attendance\App\Domain\Exceptions\AttendanceException;
use Modules\Attendance\App\Domain\Models\AttendanceSetting;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Qr\QrTokens;
use Modules\Attendance\App\Domain\Qr\StaticQrCodes;
use Modules\Attendance\App\Domain\Queries\ClassChoices;
use Modules\Attendance\App\Domain\Queries\TeacherLessons;
use Modules\Attendance\App\Domain\Support\LessonSlots;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Attendance\App\Http\Concerns\KnowsSignedInUser;
use Modules\Attendance\App\Http\Requests\ScanRequest;
use Modules\Core\App\Contracts\DTOs\StudentRecord;
use Modules\Core\App\Contracts\StudentDirectory;

/**
 * Pindai QR: the scanner page of the gate and of a lesson. A record comes
 * from a student's one-time QR code or from picking the student by name
 * or NIS; both go through the same endpoint.
 */
final class ScanController
{
    use KnowsSignedInUser;

    public function index(Request $request, ClassChoices $choices, LessonSlots $lessonSlots, TeacherLessons $teacherLessons, SchoolClock $clock): Response
    {
        $this->authorizeAny();

        $today = $clock->today();
        $now = $clock->now()->format('H:i');
        $ownLessonOnly = $this->limitedToOwnLesson();

        $classes = $choices->for($this->signedInUserId());
        $slots = [];
        $current = null;

        if ($ownLessonOnly) {
            $running = $this->runningLesson($teacherLessons, $today);

            $classes = $running === null ? [] : array_values(array_filter(
                $classes,
                fn (array $option): bool => $option['value'] === (string) $running['classId'],
            ));
            $slots = $running === null ? [] : [[
                'value' => (string) $running['slotId'],
                'label' => "{$running['className']} · {$running['subjectName']} · {$running['startsAt']}–{$running['endsAt']}",
            ]];
            $current = $running === null ? null : (string) $running['slotId'];
        } else {
            foreach ($lessonSlots->on($today) as $index => $slot) {
                $slots[] = ['value' => (string) $slot->id, 'label' => 'Jam ke-'.($index + 1)." · {$slot->startsAt}–{$slot->endsAt}"];

                if ($slot->startsAt <= $now && $now < $slot->endsAt) {
                    $current = (string) $slot->id;
                }
            }
        }

        return Inertia::render('Attendance/Scan', [
            'date' => ['iso' => $today, 'label' => $clock->dateLabel($today)],
            'can' => [
                'gate' => Gate::allows('attendance.gate.use'),
                'lesson' => Gate::allows('attendance.lesson.use'),
            ],
            'ownLessonOnly' => $ownLessonOnly,
            'classes' => $classes,
            'classId' => $classes[0]['value'] ?? '',
            'slots' => $slots,
            'slotId' => $current ?? ($slots[0]['value'] ?? ''),
        ]);
    }

    /**
     * Record one student. A QR code is used up even when the record is
     * then refused, so the same picture never works twice.
     */
    public function store(
        ScanRequest $request,
        QrTokens $tokens,
        StaticQrCodes $staticCodes,
        StudentDirectory $students,
        RecordGateCheckIn $checkIn,
        RecordGateCheckOut $checkOut,
        MarkLessonPresence $markLesson,
        TeacherLessons $teacherLessons,
        SchoolClock $clock,
    ): JsonResponse {
        $token = $request->token();
        $method = $token === null ? RecordMethod::Manual : RecordMethod::Qr;

        if ($token !== null && $staticCodes->looksStatic($token)) {
            if (! AttendanceSetting::staticQrEnabled()) {
                return $this->refused('QR statis sedang dimatikan oleh sekolah. Pakai QR dari aplikasi siswa atau pilih nama siswa.');
            }

            $studentId = $staticCodes->studentIdFrom($token);
        } else {
            $studentId = $token === null
                ? (int) $request->validated('student_id')
                : $tokens->consume($token);
        }

        if ($studentId === null) {
            return $this->refused('Kode QR tidak dikenal atau sudah kedaluwarsa. Minta siswa menampilkan kode baru.');
        }

        try {
            if ($request->mode() === ScanRequest::LESSON && $this->limitedToOwnLesson()) {
                $this->assertOwnLesson($teacherLessons, $clock->today(), (int) $request->validated('class_id'), (int) $request->validated('period_slot_id'));
            }

            $outcome = match ($request->mode()) {
                ScanRequest::GATE_IN => $this->gateIn($checkIn->handle($studentId, $method, $this->signedInUserId()), $clock),
                ScanRequest::GATE_OUT => $this->gateOut($checkOut->handle($studentId, $method, $this->signedInUserId()), $clock),
                default => $this->lesson($markLesson->handle(
                    (int) $request->validated('class_id'),
                    (int) $request->validated('period_slot_id'),
                    $studentId,
                    $method,
                    $this->signedInUserId(),
                )->scanned_at, $clock),
            };
        } catch (AttendanceException $exception) {
            return $this->refused($exception->getMessage());
        }

        return response()->json(['student' => $this->student($students->find($studentId)), ...$outcome]);
    }

    /**
     * Active students matching a name or NIS, for the manual pick.
     */
    public function students(Request $request, StudentDirectory $students): JsonResponse
    {
        $this->authorizeAny();

        $term = $request->query('q');

        return response()->json([
            'students' => array_map($this->student(...), is_string($term) ? $students->search($term) : []),
        ]);
    }

    /**
     * @return array{time: ?string, status: string}
     */
    private function gateIn(DailyAttendance $row, SchoolClock $clock): array
    {
        return ['time' => $clock->time($row->checked_in_at), 'status' => $row->status->label()];
    }

    /**
     * @return array{time: ?string, status: string}
     */
    private function gateOut(DailyAttendance $row, SchoolClock $clock): array
    {
        return ['time' => $clock->time($row->checked_out_at), 'status' => $row->left_early ? 'Pulang awal' : 'Pulang'];
    }

    /**
     * @return array{time: ?string, status: string}
     */
    private function lesson(?CarbonInterface $scannedAt, SchoolClock $clock): array
    {
        return ['time' => $clock->time($scannedAt), 'status' => AttendanceStatus::Present->label()];
    }

    /**
     * @return array{id: int, name: string, nis: string, class: ?string}|null
     */
    private function student(?StudentRecord $student): ?array
    {
        return $student === null ? null : [
            'id' => $student->id,
            'name' => $student->name,
            'nis' => $student->nis,
            'class' => $student->className,
        ];
    }

    /**
     * A refusal in the shape of a validation error, so the page reads it
     * the same way as a rejected field.
     */
    private function refused(string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => ['scan' => [$message]]], 422);
    }

    /**
     * A teacher (lesson recording only for their own classes) scans only
     * their own lessons; the school-wide permission keeps the free choice.
     */
    private function limitedToOwnLesson(): bool
    {
        return ! Gate::allows('attendance.lesson.school');
    }

    /**
     * The lesson of the signed-in teacher that is in its hour now.
     *
     * @return array{slotId: int, startsAt: string, endsAt: string, classId: int, className: string, subjectName: string}|null
     */
    private function runningLesson(TeacherLessons $teacherLessons, string $today): ?array
    {
        $userId = $this->signedInUserId();

        if ($userId === null) {
            return null;
        }

        foreach ($teacherLessons->on($userId, $today) as $lesson) {
            if ($lesson['state'] === LessonState::Running->value) {
                return $lesson;
            }
        }

        return null;
    }

    /**
     * @throws AttendanceException
     */
    private function assertOwnLesson(TeacherLessons $teacherLessons, string $today, int $classId, int $slotId): void
    {
        $userId = $this->signedInUserId();
        $lesson = $userId === null ? null : $teacherLessons->find($userId, $today, $slotId);

        if ($lesson === null || $lesson['classId'] !== $classId) {
            throw new AttendanceException('Jam ini bukan jadwal mengajar Anda.');
        }
    }

    private function authorizeAny(): void
    {
        abort_unless(Gate::allows('attendance.scan.use'), 403);
    }
}
