<?php

namespace Modules\Ppdb\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Support\ResultAnnouncer;
use Modules\Ppdb\Tests\Feature\Support\RecordingResultAnnouncer;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';
require_once __DIR__.'/Support/RecordingResultAnnouncer.php';

/*
 * Seleksi & Pengumuman: ranking by score within a path, decisions within
 * the path's quota, and announcing the results of the period.
 */

it('ranks the verified applicants of a path by score, those without a score last', function () {
    [$tenant, $period, $wave, $path] = selectionSchool('seleksi-urutan');
    $other = ppdbPath($tenant, $period, ['name' => 'Prestasi', 'sort_order' => 1]);
    ppdbApplicant($tenant, $period, $wave, $other, ['name' => 'Di Jalur Lain', 'status' => ApplicantStatus::Verified, 'score' => '99.00']);
    ppdbApplicant($tenant, $period, $wave, $path, ['name' => 'Belum Terverifikasi', 'score' => '95.00']);

    get(school($tenant->slug, '/ppdb/seleksi'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Ppdb/Selection')
        ->where('selectedPathId', $path->id)
        ->where('candidates', fn ($rows) => collect($rows)->map(fn ($row) => [$row['name'], $row['rank'], $row['score']])->all() === [
            ['Anindya', 1, '92.00'],
            ['Bima', 2, '88.40'],
            ['Citra', null, null],
        ])
        ->where('quota', ['capacity' => 2, 'accepted' => 0, 'waitlist' => 0])
        ->where('pending', 3 + 1)
        ->has('paths', 2)
    );

    get(school($tenant->slug, "/ppdb/seleksi?jalur={$other->id}"))->assertInertia(fn (Assert $page) => $page
        ->where('selectedPathId', $other->id)
        ->where('candidates', fn ($rows) => collect($rows)->pluck('name')->all() === ['Di Jalur Lain'])
    );
});

it('tells the page there is no period when none exists', function () {
    $tenant = ppdbTenant(slug: 'seleksi-kosong');

    get(school($tenant->slug, '/ppdb/seleksi'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('period', null)
        ->where('candidates', [])
    );
});

it('saves scores and decisions within the quota', function () {
    [$tenant, , , $path, $applicants] = selectionSchool('seleksi-simpan');

    put(school($tenant->slug, '/ppdb/seleksi'), selectionBody($path->id, [
        [$applicants['Anindya'], '92', 'accepted'],
        [$applicants['Bima'], '88.4', 'accepted'],
        [$applicants['Citra'], '71.5', 'waitlist'],
    ]))->assertSessionHasNoErrors();

    $saved = ppdbSchool($tenant, fn () => Applicant::query()->orderBy('number')->get(['name', 'score', 'decision'])->map(fn ($a) => [$a->name, number_format((float) $a->score, 2, '.', ''), $a->decision])->all());

    expect($saved)->toBe([
        ['Anindya', '92.00', Decision::Accepted],
        ['Bima', '88.40', Decision::Accepted],
        ['Citra', '71.50', Decision::Waitlist],
    ]);

    get(school($tenant->slug, '/ppdb/seleksi'))->assertInertia(fn (Assert $page) => $page
        ->where('quota', ['capacity' => 2, 'accepted' => 2, 'waitlist' => 1])
        ->where('candidates.2.rank', 3)
    );
});

it('refuses more accepted than the quota and saves nothing at all', function () {
    [$tenant, , , $path, $applicants] = selectionSchool('seleksi-kuota', quota: 1);

    put(school($tenant->slug, '/ppdb/seleksi'), selectionBody($path->id, [
        [$applicants['Anindya'], '92', 'accepted'],
        [$applicants['Bima'], '88.4', 'accepted'],
    ]))->assertSessionHasErrors('quota');

    expect(ppdbSchool($tenant, fn () => Applicant::query()->where('decision', 'accepted')->count()))->toBe(0)
        ->and(ppdbSchool($tenant, fn () => $applicants['Anindya']->fresh()->decision))->toBe(Decision::Pending);
});

it('counts accepted applicants that are not in the request towards the quota', function () {
    [$tenant, , , $path, $applicants] = selectionSchool('seleksi-kuota-lama', quota: 1);
    ppdbSchool($tenant, fn () => $applicants['Anindya']->forceFill(['decision' => Decision::Accepted])->save());

    put(school($tenant->slug, '/ppdb/seleksi'), selectionBody($path->id, [
        [$applicants['Bima'], '88.4', 'accepted'],
    ]))->assertSessionHasErrors('quota');

    expect(ppdbSchool($tenant, fn () => $applicants['Bima']->fresh()->decision))->toBe(Decision::Pending);
});

it('refuses a decision without a score, a bad score and a repeated applicant', function (array $row, string $field) {
    [$tenant, , , $path, $applicants] = selectionSchool('seleksi-nilai');

    put(school($tenant->slug, '/ppdb/seleksi'), selectionBody($path->id, [
        [$applicants['Citra'], $row['score'], $row['decision']],
    ]))->assertSessionHasErrors($field);

    expect(ppdbSchool($tenant, fn () => $applicants['Citra']->fresh()->decision))->toBe(Decision::Pending);
})->with([
    'decision without a score' => [['score' => null, 'decision' => 'accepted'], 'rows.0.score'],
    'score over 100' => [['score' => '101', 'decision' => 'pending'], 'rows.0.score'],
    'negative score' => [['score' => '-1', 'decision' => 'pending'], 'rows.0.score'],
    'three decimals' => [['score' => '80.123', 'decision' => 'pending'], 'rows.0.score'],
    'score in words' => [['score' => 'tinggi', 'decision' => 'pending'], 'rows.0.score'],
    'unknown decision' => [['score' => '80', 'decision' => 'lulus'], 'rows.0.decision'],
]);

it('lets a decision be withdrawn before the announcement', function () {
    [$tenant, , , $path, $applicants] = selectionSchool('seleksi-tarik');
    ppdbSchool($tenant, fn () => $applicants['Anindya']->forceFill(['decision' => Decision::Accepted])->save());

    put(school($tenant->slug, '/ppdb/seleksi'), selectionBody($path->id, [
        [$applicants['Anindya'], '92', 'pending'],
    ]))->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => $applicants['Anindya']->fresh()->decision))->toBe(Decision::Pending);
});

it('refuses applicants who are not verified, of another path, or who have re-registered', function () {
    [$tenant, $period, $wave, $path, $applicants] = selectionSchool('seleksi-salah');
    $other = ppdbPath($tenant, $period, ['name' => 'Prestasi', 'sort_order' => 1]);
    $waiting = ppdbApplicant($tenant, $period, $wave, $path, ['name' => 'Menunggu', 'number' => 'PPDB-27-0004']);
    $elsewhere = ppdbApplicant($tenant, $period, $wave, $other, ['name' => 'Jalur Lain', 'number' => 'PPDB-27-0005', 'status' => ApplicantStatus::Verified]);
    $enrolled = ppdbApplicant($tenant, $period, $wave, $path, ['name' => 'Sudah Daftar Ulang', 'number' => 'PPDB-27-0006', 'status' => ApplicantStatus::Verified, 'score' => '90.00', 'decision' => Decision::Accepted, 'enrolled_at' => '2027-07-20 08:00:00']);
    ppdbSchool($tenant, fn () => $path->update(['quota' => 5]));
    $url = school($tenant->slug, '/ppdb/seleksi');

    put($url, selectionBody($path->id, [[$waiting, '80', 'waitlist']]))->assertSessionHasErrors('rows.0.applicant_id');
    put($url, selectionBody($path->id, [[$elsewhere, '80', 'waitlist']]))->assertSessionHasErrors('rows.0.applicant_id');
    put($url, selectionBody($path->id, [[$enrolled, '90', 'rejected']]))->assertSessionHasErrors('rows.0.decision');
    // Sending the same row unchanged is no change and no error.
    put($url, selectionBody($path->id, [[$enrolled, '90', 'accepted']]))->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => $enrolled->fresh()->decision))->toBe(Decision::Accepted)
        ->and(ppdbSchool($tenant, fn () => $applicants['Anindya']->fresh()->decision))->toBe(Decision::Pending);
});

it('refuses to announce while a verified applicant is still undecided', function () {
    [$tenant, $period, , , $applicants] = selectionSchool('umumkan-belum');
    $this->app->instance(ResultAnnouncer::class, $spy = new RecordingResultAnnouncer);
    ppdbSchool($tenant, fn () => $applicants['Anindya']->forceFill(['decision' => Decision::Accepted])->save());

    post(school($tenant->slug, '/ppdb/seleksi/umumkan'), ['period_id' => $period->id])->assertSessionHasErrors('publish');

    expect(ppdbSchool($tenant, fn () => $period->fresh()->results_published_at))->toBeNull()
        ->and($spy->announced)->toBe([]);
});

it('refuses to announce a period nobody applied to', function () {
    $tenant = ppdbTenant(slug: 'umumkan-kosong');
    [$period] = ppdbSetup($tenant);

    post(school($tenant->slug, '/ppdb/seleksi/umumkan'), ['period_id' => $period->id])->assertSessionHasErrors('publish');

    expect(ppdbSchool($tenant, fn () => $period->fresh()->results_published_at))->toBeNull();
});

it('announces once and tells every applicant who has a decision', function () {
    [$tenant, $period, $wave, $path, $applicants] = selectionSchool('umumkan-ya');
    $this->app->instance(ResultAnnouncer::class, $spy = new RecordingResultAnnouncer);
    ppdbSchool($tenant, fn () => $applicants['Anindya']->forceFill(['decision' => Decision::Accepted])->save());
    ppdbSchool($tenant, fn () => $applicants['Bima']->forceFill(['decision' => Decision::Waitlist])->save());
    ppdbSchool($tenant, fn () => $applicants['Citra']->forceFill(['decision' => Decision::Rejected, 'score' => '50.00'])->save());
    // Still waiting for verification: nothing to announce about them.
    ppdbApplicant($tenant, $period, $wave, $path, ['name' => 'Menunggu', 'number' => 'PPDB-27-0004']);
    $this->travelTo('2027-07-01 03:00:00');

    post(school($tenant->slug, '/ppdb/seleksi/umumkan'), ['period_id' => $period->id])->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => $period->fresh()->results_published_at))->not->toBeNull()
        ->and($spy->announced)->toBe(['PPDB-27-0001', 'PPDB-27-0002', 'PPDB-27-0003']);

    // A second announcement is refused and tells nobody again.
    post(school($tenant->slug, '/ppdb/seleksi/umumkan'), ['period_id' => $period->id])->assertSessionHasErrors('publish');
    expect($spy->announced)->toHaveCount(3);

    get(school($tenant->slug, '/ppdb/seleksi'))->assertInertia(fn (Assert $page) => $page
        ->where('period.resultsPublished', true)
        ->where('period.publishedOn', '1 Juli 2027')
    );
});

it('shows each applicant their own decision on their page once the results are announced', function () {
    [$tenant, $period, , , $applicants] = selectionSchool('umumkan-akun');
    $account = ppdbAccount($tenant);
    ppdbSchool($tenant, fn () => $applicants['Anindya']->forceFill(['account_id' => $account->id, 'decision' => Decision::Accepted])->save());
    ppdbSchool($tenant, fn () => $applicants['Bima']->forceFill(['decision' => Decision::Rejected])->save());
    ppdbSchool($tenant, fn () => $applicants['Citra']->forceFill(['decision' => Decision::Waitlist, 'score' => '50.00'])->save());

    get('http://localhost/calon-siswa')->assertInertia(fn (Assert $page) => $page->where('portal.application.decision', null));

    ppdbMember($tenant, 'admin-sekolah');
    post(school($tenant->slug, '/ppdb/seleksi/umumkan'), ['period_id' => $period->id])->assertSessionHasNoErrors();

    \Pest\Laravel\actingAs($account, 'ppdb');
    get('http://localhost/calon-siswa')->assertInertia(fn (Assert $page) => $page
        ->where('portal.application.decision', 'accepted')
        ->where('portal.application.decisionLabel', 'Diterima')
    );
});

it('allows only moving someone up from the waiting list once the results are announced', function () {
    [$tenant, $period, , $path, $applicants] = selectionSchool('umumkan-naik', quota: 2);
    $this->app->instance(ResultAnnouncer::class, $spy = new RecordingResultAnnouncer);
    ppdbSchool($tenant, fn () => $applicants['Anindya']->forceFill(['decision' => Decision::Accepted])->save());
    ppdbSchool($tenant, fn () => $applicants['Bima']->forceFill(['decision' => Decision::Waitlist])->save());
    ppdbSchool($tenant, fn () => $applicants['Citra']->forceFill(['decision' => Decision::Rejected, 'score' => '50.00'])->save());
    post(school($tenant->slug, '/ppdb/seleksi/umumkan'), ['period_id' => $period->id]);
    $spy->announced = [];
    $url = school($tenant->slug, '/ppdb/seleksi');

    // Not allowed any more: a new score, withdrawing, rejecting, accepting someone rejected.
    put($url, selectionBody($path->id, [[$applicants['Anindya'], '92', 'pending']]))->assertSessionHasErrors('rows.0.decision');
    put($url, selectionBody($path->id, [[$applicants['Bima'], '70', 'accepted']]))->assertSessionHasErrors('rows.0.decision');
    put($url, selectionBody($path->id, [[$applicants['Bima'], '88.4', 'rejected']]))->assertSessionHasErrors('rows.0.decision');
    put($url, selectionBody($path->id, [[$applicants['Citra'], '50', 'accepted']]))->assertSessionHasErrors('rows.0.decision');
    expect($spy->announced)->toBe([]);

    // Moving up from the waiting list, within the quota, is allowed and the applicant is told.
    put($url, selectionBody($path->id, [[$applicants['Bima'], '88.4', 'accepted']]))->assertSessionHasNoErrors();
    expect(ppdbSchool($tenant, fn () => $applicants['Bima']->fresh()->decision))->toBe(Decision::Accepted)
        ->and($spy->announced)->toBe(['PPDB-27-0002']);
});

it('keeps moving up within the quota after the announcement', function () {
    [$tenant, $period, , $path, $applicants] = selectionSchool('umumkan-naik-kuota', quota: 1);
    ppdbSchool($tenant, fn () => $applicants['Anindya']->forceFill(['decision' => Decision::Accepted])->save());
    ppdbSchool($tenant, fn () => $applicants['Bima']->forceFill(['decision' => Decision::Waitlist])->save());
    ppdbSchool($tenant, fn () => $applicants['Citra']->forceFill(['decision' => Decision::Rejected, 'score' => '50.00'])->save());
    post(school($tenant->slug, '/ppdb/seleksi/umumkan'), ['period_id' => $period->id]);

    put(school($tenant->slug, '/ppdb/seleksi'), selectionBody($path->id, [[$applicants['Bima'], '88.4', 'accepted']]))
        ->assertSessionHasErrors('quota');

    expect(ppdbSchool($tenant, fn () => $applicants['Bima']->fresh()->decision))->toBe(Decision::Waitlist);
});

it('does not let the quota be set below the number already accepted', function () {
    [$tenant, $period, , $path, $applicants] = selectionSchool('seleksi-turun-kuota', quota: 2);
    ppdbSchool($tenant, fn () => $applicants['Anindya']->forceFill(['decision' => Decision::Accepted])->save());
    ppdbSchool($tenant, fn () => $applicants['Bima']->forceFill(['decision' => Decision::Accepted])->save());
    $url = school($tenant->slug, "/ppdb/pengaturan/periode/{$period->id}/jalur");

    put($url, ['paths' => [['id' => $path->id, 'name' => 'Zonasi', 'quota' => 1]]])->assertSessionHasErrors('paths.0.quota');
    expect(ppdbSchool($tenant, fn () => $path->fresh()->quota))->toBe(2);

    put($url, ['paths' => [['id' => $path->id, 'name' => 'Zonasi', 'quota' => 2]]])->assertSessionHasNoErrors();
    put($url, ['paths' => [['id' => $path->id, 'name' => 'Zonasi', 'quota' => 5]]])->assertSessionHasNoErrors();
    expect(ppdbSchool($tenant, fn () => $path->fresh()->quota))->toBe(5);
});

it('lets the committee staff read the selection but only the admin write it', function (string $role, int $read, int $write) {
    [$tenant, $period, , $path, $applicants] = selectionSchool("seleksi-izin-{$role}", role: $role);

    get(school($tenant->slug, '/ppdb/seleksi'))->assertStatus($read);
    put(school($tenant->slug, '/ppdb/seleksi'), selectionBody($path->id, [[$applicants['Anindya'], '92', 'accepted']]))->assertStatus($write);
    post(school($tenant->slug, '/ppdb/seleksi/umumkan'), ['period_id' => $period->id])->assertStatus($write);

    // Saved for the admin, untouched for everyone else.
    expect(ppdbSchool($tenant, fn () => $applicants['Anindya']->fresh()->decision))->toBe($write === 302 ? Decision::Accepted : Decision::Pending);
})->with([
    'admin' => ['admin-sekolah', 200, 302],
    'staf' => ['staf-tu', 200, 403],
    'guru' => ['guru', 403, 403],
]);

it('keeps another school out of the selection', function () {
    [$tenant, , , $path] = selectionSchool('seleksi-sekolah-a');
    [$other, $otherPeriod, , $otherPath, $foreign] = selectionSchool('seleksi-sekolah-b');
    ppdbMember($tenant, 'admin-sekolah');

    get(school($tenant->slug, '/ppdb/seleksi'))->assertInertia(fn (Assert $page) => $page
        ->where('paths', fn ($paths) => collect($paths)->pluck('id')->all() === [$path->id])
    );

    // Another school's path, applicants and period are simply not found.
    put(school($tenant->slug, '/ppdb/seleksi'), selectionBody($otherPath->id, [[$foreign['Anindya'], '92', 'accepted']]))->assertNotFound();
    put(school($tenant->slug, '/ppdb/seleksi'), selectionBody($path->id, [[$foreign['Anindya'], '92', 'accepted']]))->assertSessionHasErrors('rows.0.applicant_id');
    post(school($tenant->slug, '/ppdb/seleksi/umumkan'), ['period_id' => $otherPeriod->id])->assertNotFound();

    expect(ppdbSchool($other, fn () => $foreign['Anindya']->fresh()->decision))->toBe(Decision::Pending)
        ->and(ppdbSchool($other, fn () => $otherPeriod->fresh()->results_published_at))->toBeNull();
});
