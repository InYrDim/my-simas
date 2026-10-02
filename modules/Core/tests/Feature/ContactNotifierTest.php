<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Core\App\Contracts\ContactNotifier;
use Modules\Core\App\Contracts\DTOs\ContactNotice;
use Modules\Core\App\Contracts\Exceptions\UnknownNoticeKindException;
use Modules\Core\App\Domain\Enums\WhatsappMessageStatus;
use Modules\Core\App\Domain\Models\WhatsappMessage;
use Modules\Core\App\Domain\Models\WhatsappNoticeSetting;
use Modules\Core\App\Infrastructure\Whatsapp\DeliverWhatsappMessage;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

require_once __DIR__.'/Support/helpers.php';
require_once __DIR__.'/Support/notices.php';

/*
 * Notices to someone who has no student record (an admissions applicant's
 * guardian): the same switch, wording and log as notices to guardians, but
 * addressed by name and number. Nothing is delivered here: the queue is
 * faked.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake();
});

function contactSwitch(Tenant $tenant, bool $enabled = true, ?string $template = null): void
{
    inSchool($tenant, fn () => WhatsappNoticeSetting::query()->updateOrCreate(
        ['kind' => 'uji.absent'],
        ['enabled' => $enabled, 'template' => $template],
    ));
}

/**
 * @param  array<string, string>  $variables
 */
function notifyContact(Tenant $tenant, ?string $phone = '0812-3456-7890', array $variables = ['status' => 'izin', 'tanggal' => '2 Oktober 2026'], string $kind = 'uji.absent'): void
{
    inSchool($tenant, fn () => app(ContactNotifier::class)->notify(new ContactNotice($kind, 'Ibu Santoso', $phone, 'Budi Santoso', $variables)));
}

it('sends the filled-in wording to the name and number it was given when the school switched the kind on', function () {
    registerAbsenceNotice();
    $tenant = schoolAs('kontak-on');
    contactSwitch($tenant);

    notifyContact($tenant);

    $message = inSchool($tenant, fn () => WhatsappMessage::query()->sole());

    expect($message->status)->toBe(WhatsappMessageStatus::Pending)
        ->and($message->kind)->toBe('uji.absent')
        ->and($message->student_id)->toBeNull()
        ->and($message->recipient_name)->toBe('Ibu Santoso')
        ->and($message->phone)->toBe('6281234567890')
        ->and($message->body)->toBe("Yth. Ibu Santoso, Budi Santoso tercatat izin pada 2 Oktober 2026. — {$tenant->name}");

    Queue::assertPushed(DeliverWhatsappMessage::class, fn (DeliverWhatsappMessage $job): bool => $job->messageId === $message->id);
});

it('does nothing while the kind is off', function (?bool $enabled) {
    registerAbsenceNotice();
    $tenant = schoolAs('kontak-off');

    if ($enabled !== null) {
        contactSwitch($tenant, $enabled);
    }

    notifyContact($tenant);

    expect(inSchool($tenant, fn () => WhatsappMessage::query()->count()))->toBe(0);

    Queue::assertNothingPushed();
})->with(['never switched on' => [null], 'switched off' => [false]]);

it('uses the school\'s own wording and never lets the caller replace the names', function () {
    registerAbsenceNotice();
    $tenant = schoolAs('kontak-kata');
    contactSwitch($tenant, true, '{nama_siswa} / {nama_wali} / {nama_sekolah} / {status}');

    notifyContact($tenant, variables: ['status' => 'alpa', 'nama_siswa' => 'X', 'nama_wali' => 'Y', 'nama_sekolah' => 'Z']);

    expect(inSchool($tenant, fn () => WhatsappMessage::query()->sole()->body))->toBe("Budi Santoso / Ibu Santoso / {$tenant->name} / alpa");
});

it('logs the notice without queueing it when there is no usable number', function (?string $phone) {
    registerAbsenceNotice();
    $tenant = schoolAs('kontak-tanpa-nomor');
    contactSwitch($tenant);

    notifyContact($tenant, $phone);

    $message = inSchool($tenant, fn () => WhatsappMessage::query()->sole());

    expect($message->status)->toBe(WhatsappMessageStatus::NoRecipient)
        ->and($message->phone)->toBeNull()
        ->and($message->recipient_name)->toBe('Ibu Santoso');

    Queue::assertNothingPushed();
})->with(['none' => [null], 'unusable' => ['-']]);

it('refuses a kind nobody registered', function () {
    $tenant = schoolAs('kontak-tak-dikenal');

    notifyContact($tenant, kind: 'library.overdue');
})->throws(UnknownNoticeKindException::class);

it('refuses a kind whose module the school does not have', function () {
    registerAbsenceNotice('attendance');
    $tenant = schoolAs('kontak-tanpa-modul');
    contactSwitch($tenant);

    notifyContact($tenant);
})->throws(UnknownNoticeKindException::class);

it('never uses another school\'s switch or log', function () {
    registerAbsenceNotice();

    $other = TenantFactory::new()->create(['slug' => 'kontak-lain']);
    contactSwitch($other);

    $tenant = schoolAs('kontak-sendiri');

    // Only the other school switched the kind on: nothing happens here.
    notifyContact($tenant);

    expect(inSchool($tenant, fn () => WhatsappMessage::query()->count()))->toBe(0)
        ->and(inSchool($other, fn () => WhatsappMessage::query()->count()))->toBe(0);

    Queue::assertNothingPushed();
});
