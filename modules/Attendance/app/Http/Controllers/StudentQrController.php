<?php

namespace Modules\Attendance\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Qr\QrTokens;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Core\App\Contracts\DTOs\StudentRecord;
use Modules\Core\App\Contracts\StudentDirectory;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * QR Absensi: a student's own attendance code and what was recorded for
 * the student today. Open only to an account that belongs to an active
 * student (the route also asks for `attendance.qr.show`).
 */
final class StudentQrController
{
    /**
     * Codes one account may ask for per minute. The page takes a new one
     * every 45 seconds; this leaves room for reloads.
     */
    private const TOKEN_LIMIT = 20;

    public function __construct(
        private readonly StudentDirectory $students,
        private readonly SchoolClock $clock,
    ) {}

    public function show(): Response
    {
        $student = $this->student();
        $today = $this->clock->today();
        $record = DailyAttendance::query()->where('student_id', $student->id)->where('date', $today)->first();

        return Inertia::render('Attendance/MyQr', [
            'student' => ['name' => $student->name, 'nis' => $student->nis, 'class' => $student->className],
            'today' => [
                'label' => $this->clock->dateLabel($today),
                'status' => $record?->status->label(),
                'checkedIn' => $this->clock->time($record?->checked_in_at),
                'checkedOut' => $this->clock->time($record?->checked_out_at),
            ],
            'refreshEvery' => 45,
        ]);
    }

    /**
     * A fresh one-time code for the signed-in student.
     */
    public function token(QrTokens $tokens, TenantContext $context): JsonResponse
    {
        $student = $this->student();

        $throttleKey = sprintf('attendance-qr:%s:%s', $context->currentOrFail()->id, Auth::id());

        if (RateLimiter::tooManyAttempts($throttleKey, self::TOKEN_LIMIT)) {
            return response()->json([
                'message' => 'Terlalu sering meminta kode. Coba lagi dalam '.RateLimiter::availableIn($throttleKey).' detik.',
            ], 429);
        }

        RateLimiter::hit($throttleKey, 60);

        return response()->json($tokens->issue($student->id));
    }

    /**
     * The active student behind the signed-in account; anyone else is
     * refused.
     */
    private function student(): StudentRecord
    {
        $student = $this->students->findByUserId((int) Auth::id());

        abort_if($student === null || ! $student->active, 403);

        return $student;
    }
}
