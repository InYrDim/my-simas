<?php

namespace Modules\Ppdb\Tests\Feature;

use Modules\Core\App\Contracts\ContactNotifier;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Notifications\ResultNotices;
use Modules\Ppdb\App\Domain\Support\ResultAnnouncer;
use Modules\Ppdb\App\Infrastructure\Notifications\WhatsappResultAnnouncer;
use Modules\Ppdb\Tests\Feature\Support\RecordingContactNotifier;

use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';
require_once __DIR__.'/Support/RecordingContactNotifier.php';

/*
 * The WhatsApp result notice: announcing the results of a period tells each
 * applicant's guardian, through Core's contact notifier, once. The school's
 * switch and wording are Core's (tested there); here only what PPDB asks
 * Core to send, and to whom.
 */

it('announces through WhatsApp by default', function () {
    expect(app(ResultAnnouncer::class))->toBeInstanceOf(WhatsappResultAnnouncer::class);
});

it('offers a notice kind whose wording the school can change', function () {
    $kind = ResultNotices::kinds()[0];

    expect($kind->key)->toBe('ppdb.result')
        ->and($kind->recipient)->toBe('Wali pendaftar')
        ->and(array_keys($kind->variables))->toBe(['nomor', 'jalur', 'hasil', 'keterangan'])
        ->and($kind->template)->toContain('{nama_wali}')->toContain('{nama_siswa}')->toContain('{hasil}');
});

it('tells every applicant with a decision, once, what was decided and what to do next', function () {
    [$tenant, $period, $wave, $path, $applicants] = selectionSchool('wa-umumkan');
    $this->app->instance(ContactNotifier::class, $sent = new RecordingContactNotifier);
    ppdbSchool($tenant, fn () => $applicants['Anindya']->forceFill(['decision' => Decision::Accepted, 'guardian_name' => 'Ibu Anindya', 'guardian_phone' => '081234567890'])->save());
    ppdbSchool($tenant, fn () => $applicants['Bima']->forceFill(['decision' => Decision::Waitlist])->save());
    ppdbSchool($tenant, fn () => $applicants['Citra']->forceFill(['decision' => Decision::Rejected, 'score' => '50.00'])->save());
    // Still waiting for verification: nothing to tell.
    ppdbApplicant($tenant, $period, $wave, $path, ['name' => 'Menunggu', 'number' => 'PPDB-27-0004']);

    post(school($tenant->slug, '/ppdb/seleksi/umumkan'), ['period_id' => $period->id])->assertSessionHasNoErrors();

    $notices = collect($sent->sent)->keyBy('subjectName');

    expect($notices->keys()->sort()->values()->all())->toBe(['Anindya', 'Bima', 'Citra'])
        ->and($notices['Anindya']->kind)->toBe('ppdb.result')
        ->and($notices['Anindya']->recipientName)->toBe('Ibu Anindya')
        ->and($notices['Anindya']->phone)->toBe('081234567890')
        ->and($notices['Anindya']->variables)->toBe([
            'nomor' => 'PPDB-27-0001',
            'jalur' => 'Zonasi',
            'hasil' => 'DITERIMA',
            'keterangan' => 'Silakan melakukan daftar ulang sesuai jadwal sekolah.',
        ])
        ->and($notices['Bima']->variables['hasil'])->toBe('CADANGAN')
        ->and($notices['Bima']->variables['keterangan'])->toContain('daftar cadangan')
        ->and($notices['Citra']->variables['hasil'])->toBe('TIDAK DITERIMA')
        ->and($notices['Citra']->variables['keterangan'])->toBe('Terima kasih telah mendaftar.');

    // A second announcement is refused and tells nobody again.
    post(school($tenant->slug, '/ppdb/seleksi/umumkan'), ['period_id' => $period->id])->assertSessionHasErrors('publish');

    expect($sent->sent)->toHaveCount(3);
});

it('tells the guardian when an applicant on the waiting list moves up after the announcement', function () {
    [$tenant, $period, , $path, $applicants] = selectionSchool('wa-naik', quota: 2);
    $this->app->instance(ContactNotifier::class, $sent = new RecordingContactNotifier);
    ppdbSchool($tenant, fn () => $applicants['Anindya']->forceFill(['decision' => Decision::Accepted])->save());
    ppdbSchool($tenant, fn () => $applicants['Bima']->forceFill(['decision' => Decision::Waitlist])->save());
    ppdbSchool($tenant, fn () => $applicants['Citra']->forceFill(['decision' => Decision::Rejected, 'score' => '50.00'])->save());
    post(school($tenant->slug, '/ppdb/seleksi/umumkan'), ['period_id' => $period->id]);
    $sent->sent = [];

    put(school($tenant->slug, '/ppdb/seleksi'), selectionBody($path->id, [[$applicants['Bima'], '88.4', 'accepted']]))->assertSessionHasNoErrors();

    expect($sent->sent)->toHaveCount(1)
        ->and($sent->sent[0]->subjectName)->toBe('Bima')
        ->and($sent->sent[0]->variables['hasil'])->toBe('DITERIMA');
});

it('still announces when the school has no usable notice, and says so by the answer', function () {
    [$tenant, $period, , , $applicants] = selectionSchool('wa-tolak');
    $this->app->instance(ContactNotifier::class, $sent = new RecordingContactNotifier);
    $sent->refuse = true;
    ppdbSchool($tenant, fn () => $applicants['Anindya']->forceFill(['decision' => Decision::Accepted, 'guardian_phone' => '081234567890'])->save());
    ppdbSchool($tenant, fn () => $applicants['Bima']->forceFill(['decision' => Decision::Rejected])->save());
    ppdbSchool($tenant, fn () => $applicants['Citra']->forceFill(['decision' => Decision::Rejected, 'score' => '50.00'])->save());

    post(school($tenant->slug, '/ppdb/seleksi/umumkan'), ['period_id' => $period->id])->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => $period->fresh()->results_published_at))->not->toBeNull()
        ->and($sent->sent)->toBe([])
        ->and(ppdbSchool($tenant, fn () => app(WhatsappResultAnnouncer::class)->announce($applicants['Anindya']->fresh())))->toBeFalse();
});

it('addresses a guardian who gave no name by the applicant\'s name, and answers whether a number was given', function () {
    $tenant = ppdbTenant(slug: 'wa-tanpa-wali');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $this->app->instance(ContactNotifier::class, $sent = new RecordingContactNotifier);
    $make = fn (array $attributes) => ppdbApplicant($tenant, $period, $wave, $path, [
        'name' => 'Dika', 'status' => ApplicantStatus::Verified, 'decision' => Decision::Accepted, 'guardian_name' => null, ...$attributes,
    ]);
    $announcer = fn ($applicant): bool => ppdbSchool($tenant, fn () => app(WhatsappResultAnnouncer::class)->announce($applicant));

    expect($announcer($make(['guardian_phone' => '081234567890'])))->toBeTrue()
        ->and($announcer($make(['guardian_phone' => null])))->toBeFalse()
        ->and($announcer($make(['guardian_phone' => '', 'decision' => Decision::Pending])))->toBeFalse()
        ->and($sent->sent)->toHaveCount(2)
        ->and($sent->sent[0]->recipientName)->toBe('Orang tua/wali Dika')
        ->and($sent->sent[1]->phone)->toBeNull();
});
