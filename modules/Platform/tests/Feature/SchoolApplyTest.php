<?php

use Modules\Platform\Database\Factories\TenantApplicationFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Stage 7 (Fase 2): public school application form on the central
 * host. No auth; anti-spam = IP throttle + honeypot; domain rules
 * (slug reserved/taken/dupek, duplicate pending email) live in the
 * TenantApplications contract and are proven end-to-end here over
 * HTTP. A successful submit only creates a pending row — no tenant,
 * no login, no email.
 */
it('renders the public application form on the central host', function () {
    get('http://localhost/daftar-sekolah')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Platform/SchoolApply')
                ->has('timezones', 3),
        );
});

it('serves the application form even when a school is remembered in the session', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get(school('sekolah-a', '/daftar-sekolah'))->assertOk();
});

it('stores a valid submission as a pending application', function () {
    post('http://localhost/daftar-sekolah', [
        'school_name' => 'SMA Nusantara',
        'desired_slug' => 'sma-nusantara',
        'timezone' => 'Asia/Jakarta',
        'applicant_name' => 'Budi Kepsek',
        'applicant_email' => 'budi@nusantara.test',
        'applicant_message' => 'Siap mulai semester ini.',
    ])
        ->assertRedirect(route('school.apply.create'))
        ->assertSessionHas('status');

    $row = DB::table('tenant_applications')
        ->where('desired_slug', 'sma-nusantara')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('pending')
        ->and($row->applicant_email)->toBe('budi@nusantara.test')
        // A submission creates NOTHING but the row.
        ->and(DB::table('tenants')->where('slug', 'sma-nusantara')->exists())->toBeFalse();
});

it('rejects a slug colliding with an existing tenant', function () {
    TenantFactory::new()->create(['slug' => 'sma-bentrok']);

    post('http://localhost/daftar-sekolah', [
        'school_name' => 'SMA Bentrok Baru',
        'desired_slug' => 'sma-bentrok',
        'applicant_name' => 'Budi',
        'applicant_email' => 'budi@bentrok.test',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('application');

    expect(DB::table('tenant_applications')->count())->toBe(0);
});

it('rejects a second pending application for the same email', function () {
    TenantApplicationFactory::new()->create([
        'applicant_email' => 'kepsek@dobel.test',
    ]);

    post('http://localhost/daftar-sekolah', [
        'school_name' => 'SMA Dobel',
        'desired_slug' => 'sma-dobel',
        'applicant_name' => 'Kepsek Dobel',
        'applicant_email' => 'KEPSek@dobel.test',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('application');

    expect(DB::table('tenant_applications')->where('desired_slug', 'sma-dobel')->exists())->toBeFalse();
});

it('rejects a slug that is already pending approval', function () {
    TenantApplicationFactory::new()->create(['desired_slug' => 'sma-nunggu']);

    post('http://localhost/daftar-sekolah', [
        'school_name' => 'SMA Nunggu Juga',
        'desired_slug' => 'sma-nunggu',
        'applicant_name' => 'Lain',
        'applicant_email' => 'lain@nunggu.test',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('application');
});

it('validates required fields', function () {
    post('http://localhost/daftar-sekolah', [
        'school_name' => '',
        'desired_slug' => '',
        'applicant_name' => '',
        'applicant_email' => 'bukan-email',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors(['school_name', 'desired_slug', 'applicant_name', 'applicant_email']);

    expect(DB::table('tenant_applications')->count())->toBe(0);
});

it('silently drops honeypot submissions with a generic success', function () {
    post('http://localhost/daftar-sekolah', [
        'school_name' => 'SMA Bot',
        'desired_slug' => 'sma-bot',
        'applicant_name' => 'Bot',
        'applicant_email' => 'bot@spam.test',
        'website' => 'http://spam.example',
    ])
        ->assertRedirect(route('school.apply.create'))
        ->assertSessionHas('status');

    // The row must NOT exist: the honeypot hit is dropped.
    expect(DB::table('tenant_applications')->where('desired_slug', 'sma-bot')->exists())->toBeFalse();
});
