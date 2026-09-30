<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Modules\Platform\App\Domain\Models\ProviderUser;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\TenantApplicationFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Stage 6 (Fase 2): provider console application review. Guard
 * separation (provider vs tenant users), central-host requirement, and
 * the approve-from-UI end-to-end provisioning incl. provider
 * corrections. Identity's first-admin listener does not exist yet
 * (Stage 8), so approval only provisions the tenant here.
 */
function reviewProvider(): ProviderUser
{
    /** @var ProviderUser $provider */
    $provider = ProviderUserFactory::new()->create([
        'email' => 'staff@simas.test',
    ]);

    return $provider;
}

beforeEach(function () {
    // Tenant-side probe route (real Identity routes are separate). It
    // reports the WEB guard state FROM WITHIN the request — tenant
    // routes authorize via 'web' only, so that is the security
    // property: a provider session cookie must never confer tenant
    // identity. (The provider guard itself may resolve from the shared
    // session store in-process; it is inert here because tenant auth
    // never consults it and the session keys differ per guard.)
    Route::get('/tenant-review-probe', fn (): string => 'tenant-probe|web:'
        .(Auth::guard('web')->check() ? 'in' : 'out'))
        ->middleware(['web']);
});

it('requires the provider guard for review routes', function () {
    TenantApplicationFactory::new()->create();

    // Guest on central host → redirected to provider login.
    get('http://localhost/platform/applications')
        ->assertRedirect(route('platform.login'));
});

it('rejects a tenant web session on platform review routes', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'guard-a']);
    TenantApplicationFactory::new()->create();

    // A tenant principal on the CENTRAL host, authenticated as 'web'.
    // Deliberately an anonymous Authenticatable (not Identity's User —
    // modules never import it, arch-test enforced): the console only
    // cares that a non-provider session gets no access.
    $webUser = new class extends Authenticatable
    {
        protected $table = 'users';

        public function getAuthIdentifier(): int
        {
            return 999001;
        }
    };

    DB::table('users')->insert([
        'id' => 999001,
        'tenant_id' => $tenant->id,
        'name' => 'Web User',
        'email' => 'web@guard-a.test',
        'password' => 'password',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($webUser, 'web');

    get('http://localhost/platform/applications')
        ->assertRedirect(route('platform.login'));
});

it('does not let a provider session authenticate tenant routes', function () {
    $provider = reviewProvider();
    TenantFactory::new()->create(['slug' => 'guard-b']);

    // Real provider login on the central host to obtain its session
    // cookie (actingAs persists guard state in the test process, which
    // would defeat the cross-host check).
    post('http://localhost/platform/login', [
        'email' => 'staff@simas.test',
        'password' => 'password',
    ])->assertRedirect();

    expect(Auth::guard('provider')->check())->toBeTrue();

    $sessionCookie = collect($this->app->make('cookie')->getQueuedCookies())
        ->first(fn ($cookie) => str_contains($cookie->getName(), 'session'));

    // Provider staff have no tenant_id column and no users row: even
    // with the central cookie replayed, the tenant host must see a
    // web-guard guest — no tenant identity leaks from the provider
    // session.
    get('http://guard-b.localhost/tenant-review-probe', [
        'Cookie' => $sessionCookie !== null
            ? $sessionCookie->getName().'='.$sessionCookie->getValue()
            : '',
    ])
        ->assertOk()
        ->assertSee('tenant-probe|web:out', false);
});

it('lists pending applications for the provider', function () {
    actingAs(reviewProvider(), 'provider');

    TenantApplicationFactory::new()->create([
        'school_name' => 'SMA Antrian',
        'desired_slug' => 'sma-antrian',
    ]);

    get('http://localhost/platform/applications')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Platform/Applications/Index')
                ->where('applications.0.schoolName', 'SMA Antrian')
                ->where('applications.0.status', 'pending'),
        );
});

it('shows one application with full detail', function () {
    actingAs(reviewProvider(), 'provider');

    $application = TenantApplicationFactory::new()->create([
        'school_name' => 'SMA Detail',
        'applicant_message' => 'Tolong cepat',
    ]);

    get("http://localhost/platform/applications/{$application->id}")
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Platform/Applications/Show')
                ->where('application.schoolName', 'SMA Detail')
                ->where('application.applicantMessage', 'Tolong cepat')
                ->where('application.status', 'pending'),
        );
});

it('approves from the UI end-to-end: tenant provisioned', function () {
    actingAs(reviewProvider(), 'provider');

    $application = TenantApplicationFactory::new()->create([
        'desired_slug' => 'sma-acc',
    ]);

    post("http://localhost/platform/applications/{$application->id}/approve", [
        'school_name' => 'SMA ACC',
        'desired_slug' => 'sma-acc',
        'timezone' => 'Asia/Jakarta',
    ])
        ->assertRedirect(route('platform.applications.index'));

    $tenant = Tenant::query()->where('slug', 'sma-acc')->firstOrFail();

    expect($tenant->name)->toBe('SMA ACC')
        ->and(DB::table('tenant_applications')->find($application->id)->status)->toBe('approved');
});

it('applies provider corrections from the approve form to the final tenant', function () {
    actingAs(reviewProvider(), 'provider');

    $application = TenantApplicationFactory::new()->create([
        'school_name' => 'SMA Salah Ketik',
        'desired_slug' => 'sma-salah',
    ]);

    post("http://localhost/platform/applications/{$application->id}/approve", [
        'school_name' => 'SMA Benar',
        'desired_slug' => 'sma-benar',
        'timezone' => 'Asia/Makassar',
    ])
        ->assertRedirect(route('platform.applications.index'));

    $tenant = Tenant::query()->where('slug', 'sma-benar')->firstOrFail();

    expect($tenant->name)->toBe('SMA Benar')
        ->and($tenant->timezone)->toBe('Asia/Makassar')
        ->and(Tenant::query()->where('slug', 'sma-salah')->exists())->toBeFalse();
});

it('surfaces contract validation errors on approve instead of crashing', function () {
    actingAs(reviewProvider(), 'provider');

    TenantFactory::new()->create(['slug' => 'sma-bentrok-ui']);
    $application = TenantApplicationFactory::new()->create();

    post("http://localhost/platform/applications/{$application->id}/approve", [
        'school_name' => 'SMA Bentrok',
        'desired_slug' => 'sma-bentrok-ui',
        'timezone' => 'Asia/Jakarta',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('application');

    // Nothing provisioned: the application stays pending.
    expect(DB::table('tenant_applications')->find($application->id)->status)->toBe('pending');
});

it('rejects from the UI with a note and no side effects', function () {
    actingAs(reviewProvider(), 'provider');

    $application = TenantApplicationFactory::new()->create([
        'desired_slug' => 'sma-tolak',
    ]);

    post("http://localhost/platform/applications/{$application->id}/reject", [
        'admin_note' => 'Data tidak lengkap',
    ])
        ->assertRedirect(route('platform.applications.index'));

    $row = DB::table('tenant_applications')->find($application->id);

    expect($row->status)->toBe('rejected')
        ->and($row->admin_note)->toBe('Data tidak lengkap')
        ->and(Tenant::query()->where('slug', 'sma-tolak')->exists())->toBeFalse();
});

it('validates required approve form fields', function () {
    actingAs(reviewProvider(), 'provider');

    $application = TenantApplicationFactory::new()->create();

    post("http://localhost/platform/applications/{$application->id}/approve", [
        'school_name' => '',
        'desired_slug' => '',
        'timezone' => 'Asia/Jakarta',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors(['school_name', 'desired_slug']);

    expect(DB::table('tenant_applications')->find($application->id)->status)->toBe('pending');
});
