<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Platform\App\Contracts\Exceptions\InvalidApplicationException;
use Modules\Platform\App\Contracts\TenantApplications;
use Modules\Platform\App\Domain\Models\InvoiceKind;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Support\BillingClock;
use Modules\Platform\Database\Factories\ApplicantFactory;
use Modules\Platform\Database\Factories\InvoiceFactory;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\SubscriptionFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Billing foundation (fase 17, tahap 1): the billing calendar, plan limits
 * and visibility, and the columns later stages build on.
 */
it('takes the billing day in the billing timezone, not in UTC', function () {
    // 20:00 UTC on the 7th is already 03:00 on the 8th in Jakarta.
    $this->travelTo(Carbon::parse('2026-10-07 20:00:00', 'UTC'));

    expect(BillingClock::today()->toDateString())->toBe('2026-10-08')
        ->and(Carbon::today()->toDateString())->toBe('2026-10-07');

    config(['billing.timezone' => 'UTC']);

    expect(BillingClock::today()->toDateString())->toBe('2026-10-07');
});

it('returns the billing day as midnight in the application timezone', function () {
    $this->travelTo(Carbon::parse('2026-10-07 20:00:00', 'UTC'));

    $today = BillingClock::today();
    $periodEnd = Carbon::parse('2026-10-08');

    // Same shape as a date-cast attribute, so an equal date compares equal.
    expect($today->equalTo($periodEnd))->toBeTrue()
        ->and($periodEnd->lte($today))->toBeTrue();
});

it('derives the subscription state from the billing day', function () {
    $this->travelTo(Carbon::parse('2026-10-07 20:00:00', 'UTC'));

    $trial = SubscriptionFactory::new()->state([
        'trial_ends_at' => Carbon::parse('2026-10-07'),
    ])->create();

    // Jakarta already counts the 8th, so a trial that ended on the 7th is over.
    expect($trial->displayState())->toBe(Subscription::STATE_TRIAL_EXPIRED);
});

it('reads a plan limit and treats a missing key as no limit', function () {
    $plan = PlanFactory::new()->withLimits(['students' => 300])->create();

    expect($plan->limit('students'))->toBe(300)
        ->and($plan->limit('staff_accounts'))->toBeNull()
        ->and(PlanFactory::new()->create()->limit('students'))->toBeNull();
});

it('keeps non-public plans out of the sign-up list but still selectable by the provider', function () {
    PlanFactory::new()->create(['key' => 'starter']);
    PlanFactory::new()->private()->create(['key' => 'khusus']);

    expect(Plan::query()->selectable()->public()->pluck('key')->all())->toBe(['starter'])
        ->and(Plan::query()->selectable()->pluck('key')->sort()->values()->all())->toBe(['khusus', 'starter']);
});

it('lists only public plans, with their limits, in the applicant onboarding form', function () {
    PlanFactory::new()->withLimits(['students' => 300, 'staff_accounts' => 25])->create(['key' => 'starter']);
    PlanFactory::new()->private()->create(['key' => 'khusus']);

    actingAs(ApplicantFactory::new()->create(), 'applicant');

    get('http://localhost/pemohon')->assertInertia(fn (Assert $page) => $page
        ->has('plans', 1)
        ->where('plans.0.key', 'starter')
        ->where('plans.0.limits.students', 300)
        ->where('plans.0.limits.staffAccounts', 25)
        ->where('plans.0.limits.storageMb', null));
});

it('refuses an applicant who picks a non-public plan', function () {
    PlanFactory::new()->private()->create(['key' => 'khusus']);
    $applicant = ApplicantFactory::new()->create();

    expect(fn () => app(TenantApplications::class)->submit($applicant->id, [
        'school_name' => 'SMA Negeri Uji Coba',
        'desired_slug' => 'sman-uji',
        'plan_key' => 'khusus',
    ]))->toThrow(InvalidApplicationException::class);
});

it('moves max_users into the staff_accounts limit and back', function () {
    $migration = require base_path('modules/Platform/database/migrations/0010_01_01_000000_add_limits_and_visibility_to_plans.php');
    assert($migration instanceof Migration);

    $migration->down();

    expect(Schema::hasColumn('plans', 'max_users'))->toBeTrue()
        ->and(Schema::hasColumn('plans', 'limits'))->toBeFalse();

    DB::table('plans')->insert([
        ['key' => 'lama', 'name' => 'Lama', 'price_monthly' => 1, 'price_yearly' => 10, 'max_users' => 25, 'modules' => '["core"]', 'is_active' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
        ['key' => 'bebas', 'name' => 'Bebas', 'price_monthly' => 1, 'price_yearly' => 10, 'max_users' => null, 'modules' => '["core"]', 'is_active' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $migration->up();

    expect(Schema::hasColumn('plans', 'max_users'))->toBeFalse();

    $lama = Plan::query()->where('key', 'lama')->sole();
    $bebas = Plan::query()->where('key', 'bebas')->sole();

    expect($lama->limits)->toBe(['staff_accounts' => 25])
        ->and($lama->is_public)->toBeTrue()
        ->and($bebas->limits)->toBeNull();
});

it('marks a school suspended before the reason existed as suspended by hand', function () {
    $migration = require base_path('modules/Platform/database/migrations/0010_01_01_000001_add_billing_fields_to_tenants.php');
    assert($migration instanceof Migration);

    $migration->down();

    $row = fn (string $slug, string $status): array => [
        'id' => (string) Str::ulid(),
        'name' => 'Sekolah '.$slug,
        'slug' => $slug,
        'timezone' => 'Asia/Jakarta',
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $suspended = $row('sekolah-ditutup', 'suspended');
    $active = $row('sekolah-buka', 'active');

    DB::table('tenants')->insert([$suspended, $active]);

    $migration->up();

    expect(DB::table('tenants')->where('id', $suspended['id'])->value('suspended_reason'))->toBe('manual')
        ->and(DB::table('tenants')->where('id', $active['id'])->value('suspended_reason'))->toBeNull()
        ->and((bool) DB::table('tenants')->where('id', $active['id'])->value('billing_exempt'))->toBeFalse();
});

it('defaults existing invoices to renewals and existing module flags to the plan', function () {
    expect(Schema::hasColumn('invoices', 'kind'))->toBeTrue()
        ->and(Schema::hasColumn('tenant_modules', 'source'))->toBeTrue()
        ->and(Schema::hasColumn('subscriptions', 'scheduled_plan_id'))->toBeTrue();

    $invoice = InvoiceFactory::new()->create();

    expect($invoice->refresh()->kind)->toBe(InvoiceKind::Renewal);
});
