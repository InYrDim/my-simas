<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Contracts\DTOs\GuardianNotice;
use Modules\Core\App\Contracts\Exceptions\UnknownNoticeKindException;
use Modules\Core\App\Contracts\GuardianNotifier;
use Modules\Core\App\Domain\Enums\WhatsappMessageStatus;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\WhatsappMessage;
use Modules\Core\App\Domain\Models\WhatsappNoticeSetting;
use Modules\Core\App\Infrastructure\Whatsapp\DeliverWhatsappMessage;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\get;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';
require_once __DIR__.'/Support/notices.php';

/*
 * Notices to guardians (fase 9, stage 4): kinds registered by modules,
 * the school's switch and wording, and the notifier other modules call.
 * Nothing is delivered here: the queue is faked.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake();

    // With a Vite dev server running, a full page load asks it to render
    // (SSR) over HTTP, which the stray-request guard would refuse.
    config(['inertia.ssr.enabled' => false]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function studentWithGuardian(Tenant $tenant, array $attributes = []): Student
{
    return inSchool($tenant, fn () => Student::factory()->create([
        'name' => 'Budi Santoso',
        'guardian_name' => 'Ibu Santoso',
        'guardian_phone' => '0812-3456-7890',
        ...$attributes,
    ]));
}

function switchNotice(Tenant $tenant, string $kind, bool $enabled = true, ?string $template = null): void
{
    inSchool($tenant, fn () => WhatsappNoticeSetting::query()->updateOrCreate(
        ['kind' => $kind],
        ['enabled' => $enabled, 'template' => $template],
    ));
}

/**
 * @param  array<string, string>  $variables
 */
function notifyGuardian(Tenant $tenant, int $studentId, array $variables = [], string $kind = 'uji.absent'): void
{
    inSchool($tenant, fn () => app(GuardianNotifier::class)->notify(new GuardianNotice($studentId, $kind, $variables)));
}

it('sends the guardian the filled-in wording when the school switched the kind on', function () {
    registerAbsenceNotice();
    $tenant = schoolAs('notice-on');
    $student = studentWithGuardian($tenant);
    switchNotice($tenant, 'uji.absent');

    notifyGuardian($tenant, $student->id, ['status' => 'izin', 'tanggal' => '2 Oktober 2026']);

    $message = inSchool($tenant, fn () => WhatsappMessage::query()->sole());

    expect($message->status)->toBe(WhatsappMessageStatus::Pending)
        ->and($message->kind)->toBe('uji.absent')
        ->and($message->student_id)->toBe($student->id)
        ->and($message->recipient_name)->toBe('Ibu Santoso')
        ->and($message->phone)->toBe('6281234567890')
        ->and($message->body)->toBe("Yth. Ibu Santoso, Budi Santoso tercatat izin pada 2 Oktober 2026. — {$tenant->name}");

    Queue::assertPushed(DeliverWhatsappMessage::class, fn (DeliverWhatsappMessage $job): bool => $job->messageId === $message->id);
});

it('does nothing while the kind is off', function (?bool $enabled) {
    registerAbsenceNotice();
    $tenant = schoolAs('notice-off');
    $student = studentWithGuardian($tenant);

    if ($enabled !== null) {
        switchNotice($tenant, 'uji.absent', $enabled);
    }

    notifyGuardian($tenant, $student->id);

    expect(inSchool($tenant, fn () => WhatsappMessage::query()->count()))->toBe(0);

    Queue::assertNothingPushed();
})->with(['never switched on' => [null], 'switched off' => [false]]);

it('uses the schools own wording over the default', function () {
    registerAbsenceNotice();
    $tenant = schoolAs('notice-wording');
    $student = studentWithGuardian($tenant);
    switchNotice($tenant, 'uji.absent', true, 'Info: {nama_siswa} {status}.');

    notifyGuardian($tenant, $student->id, ['status' => 'alpa']);

    expect(inSchool($tenant, fn () => WhatsappMessage::query()->sole()->body))->toBe('Info: Budi Santoso alpa.');
});

it('leaves a variable nobody filled in as written', function () {
    registerAbsenceNotice();
    $tenant = schoolAs('notice-unknown-var');
    $student = studentWithGuardian($tenant);
    switchNotice($tenant, 'uji.absent', true, '{nama_siswa} {status} {jam_ke}');

    notifyGuardian($tenant, $student->id, ['status' => 'sakit']);

    expect(inSchool($tenant, fn () => WhatsappMessage::query()->sole()->body))->toBe('Budi Santoso sakit {jam_ke}');
});

it('does not let the caller replace the student, guardian or school name', function () {
    registerAbsenceNotice();
    $tenant = schoolAs('notice-own-names');
    $student = studentWithGuardian($tenant);
    switchNotice($tenant, 'uji.absent', true, '{nama_siswa} / {nama_wali} / {nama_sekolah}');

    notifyGuardian($tenant, $student->id, ['nama_siswa' => 'X', 'nama_wali' => 'Y', 'nama_sekolah' => 'Z']);

    expect(inSchool($tenant, fn () => WhatsappMessage::query()->sole()->body))->toBe("Budi Santoso / Ibu Santoso / {$tenant->name}");
});

it('logs the notice without queueing it when the guardian has no usable number', function (?string $phone) {
    registerAbsenceNotice();
    $tenant = schoolAs('notice-no-number');
    $student = studentWithGuardian($tenant, ['guardian_phone' => $phone, 'guardian_name' => null]);
    switchNotice($tenant, 'uji.absent');

    notifyGuardian($tenant, $student->id);

    $message = inSchool($tenant, fn () => WhatsappMessage::query()->sole());

    expect($message->status)->toBe(WhatsappMessageStatus::NoRecipient)
        ->and($message->phone)->toBeNull()
        ->and($message->recipient_name)->toBe('Wali Budi Santoso');

    Queue::assertNothingPushed();
})->with(['none' => [null], 'unusable' => ['-']]);

it('refuses a kind nobody registered', function () {
    $tenant = schoolAs('notice-unknown');
    $student = studentWithGuardian($tenant);

    notifyGuardian($tenant, $student->id, [], 'library.overdue');
})->throws(UnknownNoticeKindException::class);

it('refuses a kind whose module the school does not have', function () {
    registerAbsenceNotice('attendance');
    $tenant = schoolAs('notice-no-module');
    $student = studentWithGuardian($tenant);
    switchNotice($tenant, 'uji.absent');

    notifyGuardian($tenant, $student->id);
})->throws(UnknownNoticeKindException::class);

it('never reaches a student or a switch of another school', function () {
    registerAbsenceNotice();

    $other = TenantFactory::new()->create(['slug' => 'notice-other']);
    $foreign = studentWithGuardian($other);
    switchNotice($other, 'uji.absent');

    $tenant = schoolAs('notice-own');
    $student = studentWithGuardian($tenant);

    // This school never switched the kind on: the other school's switch does not count.
    notifyGuardian($tenant, $student->id);

    // Switched on here, but the student belongs to the other school.
    switchNotice($tenant, 'uji.absent');
    notifyGuardian($tenant, $foreign->id);

    expect(inSchool($tenant, fn () => WhatsappMessage::query()->count()))->toBe(0)
        ->and(inSchool($other, fn () => WhatsappMessage::query()->count()))->toBe(0);

    Queue::assertNothingPushed();
});

it('lists registered kinds with the schools choice, then the announced ones', function () {
    // Every real kind is built, so the announced one is a made-up key.
    config(['notices.upcoming' => [['key' => 'uji.soon', 'title' => 'Segera', 'description' => 'Belum dibuat.', 'recipient' => 'Wali']]]);

    registerAbsenceNotice();
    $tenant = schoolAs('notice-list');
    switchNotice($tenant, 'uji.absent', true, 'Info: {nama_siswa} {status}.');

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('kinds.0.key', 'uji.absent')
        ->where('kinds.0.available', true)
        ->where('kinds.0.enabled', true)
        ->where('kinds.0.template', 'Info: {nama_siswa} {status}.')
        ->where('kinds.0.defaultTemplate', absenceNotice()->template)
        ->where('kinds.0.sample.status', 'sakit')
        ->where('kinds.0.sample.nama_siswa', 'Aditya Pratama')
        // Registered kinds first, then what is only announced.
        ->where('kinds', fn ($kinds) => collect($kinds)->pluck('key')->all() === ['uji.absent', 'uji.soon'])
        ->where('kinds.1.available', false)
        ->where('kinds.1.enabled', false)
    );
});

it('announces the kinds nobody built yet, and offers a kind off with its default wording', function () {
    // Every real kind is built, so the announced one is a made-up key.
    config(['notices.upcoming' => [['key' => 'uji.soon', 'title' => 'Segera', 'description' => 'Belum dibuat.', 'recipient' => 'Wali']]]);

    $tenant = schoolAs('notice-upcoming');

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('kinds', fn ($kinds) => collect($kinds)->pluck('key')->all() === ['uji.soon']
            && collect($kinds)->every(fn ($kind) => $kind['available'] === false))
    );

    registerAbsenceNotice();

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('kinds.0.available', true)
        ->where('kinds.0.enabled', false)
        ->where('kinds.0.template', absenceNotice()->template)
    );
});

it('hides a kind whose module the school does not have', function () {
    registerAbsenceNotice('attendance', 'attendance.lesson');
    $tenant = schoolAs('notice-hidden');

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('kinds', fn ($kinds) => ! collect($kinds)->pluck('key')->contains('attendance.lesson'))
    );

    put(school($tenant->slug, '/integrasi/whatsapp/pemberitahuan/attendance.lesson'), ['enabled' => true])->assertNotFound();
});

it('lets an admin switch a kind on and reword it', function () {
    registerAbsenceNotice();
    $tenant = schoolAs('notice-save');

    put(school($tenant->slug, '/integrasi/whatsapp/pemberitahuan/uji.absent'), [
        'enabled' => true,
        'template' => 'Info: {nama_siswa} {status}.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $setting = inSchool($tenant, fn () => WhatsappNoticeSetting::query()->sole());

    expect($setting->kind)->toBe('uji.absent')
        ->and($setting->enabled)->toBeTrue()
        ->and($setting->template)->toBe('Info: {nama_siswa} {status}.');

    put(school($tenant->slug, '/integrasi/whatsapp/pemberitahuan/uji.absent'), [
        'enabled' => false,
        'template' => 'Info: {nama_siswa} {status}.',
    ])->assertSessionHasNoErrors();

    expect(inSchool($tenant, fn () => WhatsappNoticeSetting::query()->sole()->enabled))->toBeFalse();
});

it('stores no wording when it is empty or the same as the default', function (?string $template) {
    registerAbsenceNotice();
    $tenant = schoolAs('notice-default');
    switchNotice($tenant, 'uji.absent', true, 'Kata-kata lama.');

    put(school($tenant->slug, '/integrasi/whatsapp/pemberitahuan/uji.absent'), [
        'enabled' => true,
        'template' => $template,
    ])->assertSessionHasNoErrors();

    expect(inSchool($tenant, fn () => WhatsappNoticeSetting::query()->sole()->template))->toBeNull();
})->with([
    'empty' => [''],
    'missing' => [null],
    'the default' => [fn () => absenceNotice()->template],
]);

it('validates the switch and the wording', function (array $payload, string $field) {
    registerAbsenceNotice();
    $tenant = schoolAs('notice-invalid');

    put(school($tenant->slug, '/integrasi/whatsapp/pemberitahuan/uji.absent'), $payload)->assertSessionHasErrors($field);

    expect(inSchool($tenant, fn () => WhatsappNoticeSetting::query()->count()))->toBe(0);
})->with([
    'no switch' => [['template' => 'Halo'], 'enabled'],
    'wording too long' => [['enabled' => true, 'template' => str_repeat('a', 1001)], 'template'],
]);

it('answers not found for a kind that does not exist', function () {
    $tenant = schoolAs('notice-missing');

    put(school($tenant->slug, '/integrasi/whatsapp/pemberitahuan/library.overdue'), ['enabled' => true])->assertNotFound();
});

it('keeps the settings away from other roles and guests', function (string $role) {
    registerAbsenceNotice();
    $tenant = schoolAs("notice-{$role}", $role);

    put(school($tenant->slug, '/integrasi/whatsapp/pemberitahuan/uji.absent'), ['enabled' => true])->assertForbidden();

    signOutOfSchool();

    put(school($tenant->slug, '/integrasi/whatsapp/pemberitahuan/uji.absent'), ['enabled' => true])->assertRedirect();

    expect(inSchool($tenant, fn () => WhatsappNoticeSetting::query()->count()))->toBe(0);
})->with(['guru', 'staf-tu', 'siswa']);

it('keeps each schools settings to itself', function () {
    registerAbsenceNotice();

    $other = TenantFactory::new()->create(['slug' => 'notice-settings-other']);
    switchNotice($other, 'uji.absent', true, 'Kata-kata sekolah lain.');

    $tenant = schoolAs('notice-settings-own');

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('kinds.0.enabled', false)
        ->where('kinds.0.template', absenceNotice()->template)
    );

    put(school($tenant->slug, '/integrasi/whatsapp/pemberitahuan/uji.absent'), ['enabled' => false])->assertSessionHasNoErrors();

    expect(inSchool($other, fn () => WhatsappNoticeSetting::query()->sole()->enabled))->toBeTrue()
        ->and(inSchool($other, fn () => WhatsappNoticeSetting::query()->sole()->template))->toBe('Kata-kata sekolah lain.');
});

it('names a notice by its title in the message log', function () {
    registerAbsenceNotice();
    $tenant = schoolAs('notice-log');
    $student = studentWithGuardian($tenant);
    switchNotice($tenant, 'uji.absent');

    notifyGuardian($tenant, $student->id);

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('history.0.kind', 'Siswa tidak hadir')
        ->where('history.0.recipient', 'Ibu Santoso')
        ->where('history.0.status', 'pending')
    );
});

it('ends the message with the default reply line when the school asks for it', function () {
    registerAbsenceNotice();
    $tenant = schoolAs('notice-footer-default');
    $student = studentWithGuardian($tenant);
    switchNotice($tenant, 'uji.absent', true, 'Info: {nama_siswa}.');
    inSchool($tenant, fn () => WhatsappNoticeSetting::query()->update(['reply_footer' => true]));

    notifyGuardian($tenant, $student->id);

    $body = inSchool($tenant, fn () => WhatsappMessage::query()->sole()->body);

    expect($body)->toStartWith("Info: Budi Santoso.\n\n")
        ->toMatch('/\n\n(Mohon|Silakan) balas pesan ini dengan "OK" (jika|bila) pesan sudah diterima\.$/');
});

it('ends the message with the schools own reply line, variables included', function () {
    registerAbsenceNotice();
    $tenant = schoolAs('notice-footer-own');
    $student = studentWithGuardian($tenant);
    switchNotice($tenant, 'uji.absent', true, 'Info: {nama_siswa}.');
    inSchool($tenant, fn () => WhatsappNoticeSetting::query()->update([
        'reply_footer' => true,
        'reply_footer_text' => 'Balas ya, {nama_wali}.',
    ]));

    notifyGuardian($tenant, $student->id);

    expect(inSchool($tenant, fn () => WhatsappMessage::query()->sole()->body))
        ->toBe("Info: Budi Santoso.\n\nBalas ya, Ibu Santoso.");
});

it('adds no reply line while the school did not ask for one', function () {
    registerAbsenceNotice();
    $tenant = schoolAs('notice-footer-off');
    $student = studentWithGuardian($tenant);
    switchNotice($tenant, 'uji.absent', true, 'Info: {nama_siswa}.');
    inSchool($tenant, fn () => WhatsappNoticeSetting::query()->update(['reply_footer_text' => 'Balas ya.']));

    notifyGuardian($tenant, $student->id);

    expect(inSchool($tenant, fn () => WhatsappMessage::query()->sole()->body))->toBe('Info: Budi Santoso.');
});
