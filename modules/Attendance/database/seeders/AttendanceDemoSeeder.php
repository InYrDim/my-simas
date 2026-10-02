<?php

namespace Modules\Attendance\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Enums\RecordMethod;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Core\App\Contracts\ClassDirectory;
use Modules\Core\App\Contracts\StudentDirectory;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantDirectory;
use Modules\Platform\App\Contracts\TenantModules;

/**
 * Fills the schools that use Absensi with sample daily attendance for the
 * last school days before today, so the recap, the reports and the
 * figures have something to show. Development only; skips a school that
 * already has attendance. Run the Core demo seeder first: this one needs
 * classes and students.
 *
 *   php artisan db:seed --class="Modules\Attendance\Database\Seeders\AttendanceDemoSeeder"
 *
 * Deliberately no WithoutModelEvents: `tenant_id` is filled by a model
 * event.
 */
class AttendanceDemoSeeder extends Seeder
{
    /**
     * School days (Monday to Saturday) to fill, counting back from
     * yesterday.
     */
    private const DAYS = 20;

    public function run(TenantContext $context, TenantDirectory $tenants, TenantModules $modules): void
    {
        foreach ($tenants->all() as $tenant) {
            if ($modules->isEnabled('attendance', $tenant->id)) {
                $context->run($tenant->id, fn () => $this->seedSchool());
            }
        }
    }

    private function seedSchool(): void
    {
        if (DailyAttendance::query()->exists()) {
            return;
        }

        $clock = app(SchoolClock::class);
        $students = app(StudentDirectory::class);
        $days = $this->schoolDays($clock->now()->startOfDay());

        foreach (app(ClassDirectory::class)->ofActiveYear() as $class) {
            foreach ($students->ofClass($class->id) as $student) {
                foreach ($days as $day) {
                    $status = $this->statusFor($student->id, $day);
                    $arrival = $status->countsAsPresent()
                        ? $day->setTime(...($status === AttendanceStatus::Late ? [7, 5 + $student->id % 20] : [6, 30 + $student->id % 30]))
                        : null;

                    DailyAttendance::query()->create([
                        'student_id' => $student->id,
                        'class_id' => $class->id,
                        'date' => $day->toDateString(),
                        'status' => $status,
                        'checked_in_at' => $arrival === null ? null : $clock->stored($arrival),
                        'check_in_method' => $arrival === null ? null : RecordMethod::Qr,
                        'checked_out_at' => $arrival === null ? null : $clock->stored($day->setTime(14, $student->id % 30)),
                        'check_out_method' => $arrival === null ? null : RecordMethod::Qr,
                    ]);
                }
            }
        }
    }

    /**
     * @return list<CarbonImmutable> oldest first, on the school's clock
     */
    private function schoolDays(CarbonImmutable $today): array
    {
        $days = [];

        for ($day = $today->subDay(); count($days) < self::DAYS; $day = $day->subDay()) {
            if ($day->dayOfWeekIso !== 7) {
                $days[] = $day;
            }
        }

        return array_reverse($days);
    }

    /**
     * The same student and day always get the same status, so two fresh
     * databases look alike: mostly present, now and then something else.
     */
    private function statusFor(int $studentId, CarbonImmutable $day): AttendanceStatus
    {
        $roll = crc32("{$studentId}|{$day->toDateString()}") % 100;

        return match (true) {
            $roll < 86 => AttendanceStatus::Present,
            $roll < 92 => AttendanceStatus::Late,
            $roll < 95 => AttendanceStatus::Sick,
            $roll < 98 => AttendanceStatus::Permit,
            default => AttendanceStatus::Absent,
        };
    }
}
