<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\SubscriptionFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * The shared prop `trial` the banner at the top of every school page reads
 * (fase 17 follow-up): present while the school is on its trial or in the
 * grace period after it, for every signed-in user of the school, and null
 * for everything else.
 */
function trialSchool(string $role = 'admin-sekolah', array $tenant = []): Tenant
{
    $school = TenantFactory::new()->create(['slug' => 'sekolah-uji', ...$tenant]);
    $user = UserFactory::new()->forTenant($school->id)->create(['email' => "{$role}@sekolah-uji.test"]);

    app(TenantContext::class)->run($school->id, fn () => $user->assignTenantRole($role));

    actingAs($user);

    return $school;
}

/** The `trial` prop of an ordinary Inertia page visit. */
function trialPropOf(Tenant $school): mixed
{
    $response = get(school($school->slug, '/trial-notice-probe'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ])->assertOk();

    return $response->json('props.trial');
}

beforeEach(function () {
    Route::middleware('web')->get('/trial-notice-probe', fn (): Response => Inertia::render('welcome'));

    $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
});

it('describes a running trial: when it ends and how many days are left', function () {
    $school = trialSchool();
    SubscriptionFactory::new()->forTenant($school->id)->trialEndingIn(5)->create();

    expect(trialPropOf($school))->toBe([
        'state' => 'trial',
        'endsOn' => '2026-10-13',
        'daysLeft' => 5,
        'accessEndsOn' => '2026-10-20',
    ]);
});

it('says so on the last day, and counts days in the billing timezone', function () {
    $school = trialSchool();
    SubscriptionFactory::new()->forTenant($school->id)->trialEndingIn(0)->create();

    // 20:00 UTC on the 7th is already the 8th in Jakarta.
    $this->travelTo(Carbon::parse('2026-10-07 20:00:00', 'UTC'));

    expect(trialPropOf($school)['daysLeft'])->toBe(0)
        ->and(trialPropOf($school)['state'])->toBe('trial');
});

it('keeps showing during the grace period after the trial, with the day access stops', function () {
    $school = trialSchool();
    SubscriptionFactory::new()->forTenant($school->id)->trialEndingIn(-3)->create();

    $trial = trialPropOf($school);

    expect($trial['state'])->toBe('trial_expired')
        ->and($trial['endsOn'])->toBe('2026-10-05')
        ->and($trial['daysLeft'])->toBe(-3)
        ->and($trial['accessEndsOn'])->toBe('2026-10-12');
});

it('is shown to a teacher as well as to the school admin', function () {
    $school = trialSchool('guru');
    SubscriptionFactory::new()->forTenant($school->id)->trialEndingIn(5)->create();

    expect(trialPropOf($school)['state'])->toBe('trial');
});

it('is not sent to a school that is paying, cancelled, exempt or has no subscription', function () {
    $paying = trialSchool();
    SubscriptionFactory::new()->forTenant($paying->id)->active(20)->create();
    expect(trialPropOf($paying))->toBeNull();

    $cancelled = TenantFactory::new()->create(['slug' => 'sekolah-b']);
    $user = UserFactory::new()->forTenant($cancelled->id)->create();
    app(TenantContext::class)->run($cancelled->id, fn () => $user->assignTenantRole('admin-sekolah'));
    actingAs($user);
    SubscriptionFactory::new()->forTenant($cancelled->id)->trialEndingIn(5)->cancelled()->create();
    expect(trialPropOf($cancelled))->toBeNull();
});

it('is not sent to an exempt school or one without a subscription', function () {
    $exempt = trialSchool('admin-sekolah', ['billing_exempt' => true]);
    SubscriptionFactory::new()->forTenant($exempt->id)->trialEndingIn(5)->create();
    expect(trialPropOf($exempt))->toBeNull();

    $none = TenantFactory::new()->create(['slug' => 'sekolah-c']);
    $user = UserFactory::new()->forTenant($none->id)->create();
    app(TenantContext::class)->run($none->id, fn () => $user->assignTenantRole('admin-sekolah'));
    actingAs($user);
    expect(trialPropOf($none))->toBeNull();
});

it('is not sent to someone who is not signed in', function () {
    $school = TenantFactory::new()->create(['slug' => 'sekolah-uji']);
    SubscriptionFactory::new()->forTenant($school->id)->trialEndingIn(5)->create();

    expect(trialPropOf($school))->toBeNull();
});

it('names no other school\'s trial', function () {
    $mine = trialSchool();
    SubscriptionFactory::new()->forTenant($mine->id)->active(20)->create();
    $other = TenantFactory::new()->create(['slug' => 'sekolah-lain']);
    SubscriptionFactory::new()->forTenant($other->id)->trialEndingIn(2)->create();

    expect(trialPropOf($mine))->toBeNull();
});
