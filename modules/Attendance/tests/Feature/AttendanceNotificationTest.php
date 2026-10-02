<?php

namespace Modules\Attendance\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Core\App\Domain\Enums\WhatsappMessageStatus;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Models\TeachingAssignment;
use Modules\Core\App\Domain\Models\WhatsappMessage;
use Modules\Core\App\Domain\Models\WhatsappNoticeSetting;
use Modules\Core\App\Infrastructure\Whatsapp\DeliverWhatsappMessage;
use Modules\Platform\App\Domain\Models\Tenant;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * What attendance tells guardians over WhatsApp. The school switches each
 * kind on at Integrasi › WhatsApp; attendance only says what happened.
 * The gateway is never called: messages stop at the log and the queue.
 *
 * Friday 2 October 2026, 06:50 at the school (Asia/Jakarta).
 */
beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake();

    // With the Vite dev server running, a full page load would ask it for
    // a server-side render over HTTP — a stray request.
    config(['inertia.ssr.enabled' => false]);

    $this->travelTo('2026-10-01 23:50:00');
});

const ATTENDANCE_KINDS = ['attendance.gate-in', 'attendance.gate-out', 'attendance.absent', 'attendance.lesson-absent'];

/**
 * Switch kinds of notice on for the school (all four by default).
 *
 * @param  list<string>  $kinds
 */
function noticesOn(Tenant $tenant, array $kinds = ATTENDANCE_KINDS): void
{
    attendanceSchool($tenant, function () use ($kinds): void {
        foreach ($kinds as $kind) {
            WhatsappNoticeSetting::query()->create(['kind' => $kind, 'enabled' => true]);
        }
    });
}

/**
 * The logged messages of the school, oldest first.
 *
 * @return list<WhatsappMessage>
 */
function sentNotices(Tenant $tenant): array
{
    return attendanceSchool($tenant, fn () => WhatsappMessage::query()->orderBy('id')->get()->all());
}

/**
 * A school with one class and one student whose guardian has a number.
 *
 * @return array{0: Tenant, 1: ClassGroup, 2: Student}
 */
function noticeSchool(string $slug = 'kabar'): array
{
    $tenant = attendanceTenant(slug: $slug);
    $class = attendanceClass($tenant, 'X 1');
    $adit = attendanceStudent($tenant, $class, 'Aditya Pratama', ['guardian_name' => 'Ibu Sari', 'guardian_phone' => '0812-5550-1234']);

    return [$tenant, $class, $adit];
}

it('offers its four kinds on Integrasi WhatsApp, all switched off', function () {
    [$tenant] = noticeSchool();

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('kinds', fn ($kinds) => collect($kinds)->where('available', true)->pluck('key')->all() === ATTENDANCE_KINDS
            && collect($kinds)->where('available', true)->every(fn ($kind) => $kind['enabled'] === false))
        ->where('kinds.0.title', 'Siswa masuk sekolah')
        ->where('kinds.0.recipient', 'Wali murid')
    );
});

it('offers none of them to a school without the module', function () {
    $tenant = attendanceTenant(enabled: false);

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('kinds', fn ($kinds) => collect($kinds)->pluck('key')->intersect(ATTENDANCE_KINDS)->isEmpty())
    );
});

it('tells the guardian when the student comes in and goes home', function () {
    [$tenant, , $adit] = noticeSchool();
    noticesOn($tenant);

    postJson(school($tenant->slug, '/absensi/pindai'), ['mode' => 'gate-in', 'student_id' => $adit->id])->assertOk();

    // 14:05 at the school.
    $this->travelTo('2026-10-02 07:05:00');
    postJson(school($tenant->slug, '/absensi/pindai'), ['mode' => 'gate-out', 'student_id' => $adit->id])->assertOk();

    [$in, $out] = sentNotices($tenant);

    expect($in->kind)->toBe('attendance.gate-in')
        ->and($in->student_id)->toBe($adit->id)
        ->and($in->recipient_name)->toBe('Ibu Sari')
        ->and($in->phone)->toBe('6281255501234')
        ->and($in->status)->toBe(WhatsappMessageStatus::Pending)
        ->and($in->body)->toBe("Yth. Ibu Sari, Aditya Pratama tercatat masuk sekolah pada Jumat, 2 Oktober 2026 pukul 06.50 (Hadir). - {$tenant->name}")
        ->and($out->kind)->toBe('attendance.gate-out')
        ->and($out->body)->toBe("Yth. Ibu Sari, Aditya Pratama tercatat pulang dari sekolah pada Jumat, 2 Oktober 2026 pukul 14.05. - {$tenant->name}");

    Queue::assertPushed(DeliverWhatsappMessage::class, 2);
});

it('says late when the student came in late', function () {
    [$tenant, , $adit] = noticeSchool();
    noticesOn($tenant, ['attendance.gate-in']);

    // 07:20 at the school.
    $this->travelTo('2026-10-02 00:20:00');
    postJson(school($tenant->slug, '/absensi/pindai'), ['mode' => 'gate-in', 'student_id' => $adit->id])->assertOk();

    expect(sentNotices($tenant)[0]->body)->toContain('pukul 07.20 (Terlambat)');
});

it('tells the guardian once when the student is marked away today', function () {
    [$tenant, $class, $adit] = noticeSchool();
    noticesOn($tenant);

    $save = fn (string $status, ?string $note = null) => put(school($tenant->slug, '/absensi/input'), [
        'class_id' => $class->id, 'date' => '2026-10-02',
        'marks' => [['student_id' => $adit->id, 'status' => $status, 'note' => $note]],
    ])->assertSessionHasNoErrors();

    $save('sick', 'Demam');
    // Saving the same thing again tells nobody twice.
    $save('sick', 'Demam');

    expect(sentNotices($tenant))->toHaveCount(1)
        ->and(sentNotices($tenant)[0]->kind)->toBe('attendance.absent')
        ->and(sentNotices($tenant)[0]->body)->toBe("Yth. Ibu Sari, Aditya Pratama tercatat Sakit pada Jumat, 2 Oktober 2026. Keterangan: Demam. - {$tenant->name}");

    // A different kind of absence is news again; being present is not.
    $save('absent');
    $save('present');

    expect(sentNotices($tenant))->toHaveCount(2)
        ->and(sentNotices($tenant)[1]->body)->toContain('tercatat Alpa')->toContain('Keterangan: -.');
});

it('tells nobody about a day filled in afterwards', function () {
    [$tenant, $class, $adit] = noticeSchool();
    noticesOn($tenant);

    put(school($tenant->slug, '/absensi/input'), [
        'class_id' => $class->id, 'date' => '2026-10-01',
        'marks' => [['student_id' => $adit->id, 'status' => 'absent']],
    ])->assertSessionHasNoErrors();

    expect(sentNotices($tenant))->toBe([])
        ->and(attendanceSchool($tenant, fn () => DailyAttendance::query()->count()))->toBe(1);

    Queue::assertNothingPushed();
});

it('tells the guardian about an absence in a lesson, unless the day already says so', function () {
    [$tenant, $class, $adit] = noticeSchool();
    $bima = attendanceStudent($tenant, $class, 'Bima Sakti', ['guardian_name' => 'Pak Joko', 'guardian_phone' => '081255509999']);
    noticesOn($tenant, ['attendance.lesson-absent']);

    [$slot, $subject] = attendanceSchool($tenant, function () use ($class, $bima): array {
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        TeachingAssignment::factory()->create(['class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => Teacher::factory()->create()->id]);
        // Bima is already absent for the whole day.
        DailyAttendance::factory()->status(AttendanceStatus::Absent)->create(['student_id' => $bima->id, 'class_id' => $class->id, 'date' => '2026-10-02']);

        return [PeriodSlot::factory()->create(['day' => 5, 'start_time' => '07:15:00', 'end_time' => '08:00:00']), $subject];
    });

    $save = fn () => put(school($tenant->slug, '/absensi/jam-pelajaran'), [
        'class_id' => $class->id, 'date' => '2026-10-02', 'period_slot_id' => $slot->id, 'subject_id' => $subject->id,
        'marks' => [['student_id' => $adit->id, 'status' => 'absent'], ['student_id' => $bima->id, 'status' => 'absent']],
    ])->assertSessionHasNoErrors();

    $save();
    $save();

    $messages = sentNotices($tenant);

    expect($messages)->toHaveCount(1)
        ->and($messages[0]->kind)->toBe('attendance.lesson-absent')
        ->and($messages[0]->student_id)->toBe($adit->id)
        ->and($messages[0]->body)->toBe("Yth. Ibu Sari, Aditya Pratama tidak mengikuti pelajaran Matematika pada Jumat, 2 Oktober 2026, pukul 07.15-08.00, tanpa keterangan. - {$tenant->name}");
});

it('sends nothing for a kind the school left off', function () {
    [$tenant, $class, $adit] = noticeSchool();
    noticesOn($tenant, ['attendance.gate-out']);

    postJson(school($tenant->slug, '/absensi/pindai'), ['mode' => 'gate-in', 'student_id' => $adit->id])->assertOk();
    put(school($tenant->slug, '/absensi/input'), [
        'class_id' => $class->id, 'date' => '2026-10-02',
        'marks' => [['student_id' => $adit->id, 'status' => 'sick']],
    ])->assertSessionHasNoErrors();

    expect(sentNotices($tenant))->toBe([]);

    Queue::assertNothingPushed();
});

it('uses the wording the school chose', function () {
    [$tenant, , $adit] = noticeSchool();
    attendanceSchool($tenant, fn () => WhatsappNoticeSetting::query()->create([
        'kind' => 'attendance.gate-in', 'enabled' => true, 'template' => '{nama_siswa} masuk {jam}, {status}.',
    ]));

    postJson(school($tenant->slug, '/absensi/pindai'), ['mode' => 'gate-in', 'student_id' => $adit->id])->assertOk();

    expect(sentNotices($tenant)[0]->body)->toBe('Aditya Pratama masuk 06.50, Hadir.');
});

it('logs a guardian without a number instead of failing the record', function () {
    $tenant = attendanceTenant(slug: 'kabar-tanpa-nomor');
    $budi = attendanceStudent($tenant, attendanceClass($tenant), 'Budi', ['guardian_phone' => null]);
    noticesOn($tenant);

    postJson(school($tenant->slug, '/absensi/pindai'), ['mode' => 'gate-in', 'student_id' => $budi->id])->assertOk();

    expect(sentNotices($tenant)[0]->status)->toBe(WhatsappMessageStatus::NoRecipient)
        ->and(attendanceSchool($tenant, fn () => DailyAttendance::query()->count()))->toBe(1);

    Queue::assertNothingPushed();
});

it('keeps the notices of two schools apart', function () {
    [$other, , $foreign] = noticeSchool('kabar-lain');
    noticesOn($other);

    [$tenant, , $adit] = noticeSchool('kabar-sendiri');

    // This school left the kind off; the other one switched it on.
    postJson(school($tenant->slug, '/absensi/pindai'), ['mode' => 'gate-in', 'student_id' => $adit->id])->assertOk();

    expect(sentNotices($tenant))->toBe([])
        ->and(sentNotices($other))->toBe([]);
});
