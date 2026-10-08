<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\WhatsappChannel;
use Modules\Platform\App\Domain\Models\BillingCycle;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;
use Modules\Platform\App\Domain\Models\SuspensionReason;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantStatus;
use Modules\Platform\App\Infrastructure\Billing\BillingSummary;
use Modules\Platform\App\Infrastructure\Billing\PaymentGateway;
use Modules\Platform\App\Infrastructure\Billing\PaymentOutcome;
use Modules\Platform\App\Infrastructure\Billing\SubscriptionManager;
use Modules\Platform\App\Infrastructure\Mail\AccessReopenedMail;
use Modules\Platform\App\Infrastructure\Tenancy\TenantLifecycle;
use Modules\Platform\Database\Factories\InvoiceFactory;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\SubscriptionFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\put;

/**
 * Billing lifecycle (fase 17, tahap 4): how long a school keeps access,
 * why it can be suspended, what a suspended school sees, and what reopens
 * it.
 */
function lifecycleSubscription(Tenant $tenant, array $state = []): Subscription
{
    return SubscriptionFactory::new()->trialEndingIn(5)->forTenant($tenant->id)->create($state);
}

/** Pay the school's first invoice, the way the provider's confirmation form does. */
function lifecyclePay(Subscription $subscription): void
{
    $invoice = app(SubscriptionManager::class)->activate($subscription, $subscription->plan->key, BillingCycle::Monthly);
    $payment = app(PaymentGateway::class)->initiate($invoice);

    app(SubscriptionManager::class)->settle($payment, PaymentOutcome::paid($invoice->amount, method: 'bank_transfer', reference: 'TRF-1'));
}

describe('access dates', function () {
    it('gives a trial seven days of grace after it ends', function () {
        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
        $trial = SubscriptionFactory::new()->state(['trial_ends_at' => Carbon::parse('2026-10-01')])->create();

        expect($trial->accessEndsAt()->toDateString())->toBe('2026-10-08')
            ->and($trial->inGrace())->toBeTrue()
            ->and($trial->accessLapsed())->toBeFalse();

        $this->travelTo(Carbon::parse('2026-10-09 10:00:00', 'Asia/Jakarta'));

        expect($trial->inGrace())->toBeFalse()
            ->and($trial->accessLapsed())->toBeTrue();
    });

    it('gives a paid period seven days of grace, set apart from the trial grace', function () {
        config(['billing.trial_grace_days' => 3, 'billing.overdue_grace_days' => 10]);
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00', 'Asia/Jakarta'));

        $active = SubscriptionFactory::new()->active(-5)->create();   // ended 2026-10-15

        expect($active->accessEndsAt()->toDateString())->toBe('2026-10-25')
            ->and($active->inGrace())->toBeTrue();
    });

    it('lets a cancelled subscription keep access to the end it had, with no grace', function () {
        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
        $paid = SubscriptionFactory::new()->active(10)->cancelled()->create();
        $trial = SubscriptionFactory::new()->trialEndingIn(3)->cancelled()->create();

        expect($paid->accessEndsAt()->toDateString())->toBe($paid->current_period_end->toDateString())
            ->and($paid->accessLapsed())->toBeFalse()
            ->and($trial->accessEndsAt()->toDateString())->toBe($trial->trial_ends_at->toDateString());

        $this->travelTo($paid->current_period_end->copy()->addDay());

        expect($paid->accessLapsed())->toBeTrue()
            ->and($paid->inGrace())->toBeFalse();
    });

    it('never lapses a subscription whose dates are missing', function () {
        $subscription = SubscriptionFactory::new()->state(['status' => SubscriptionStatus::Active, 'current_period_end' => null])->create();

        expect($subscription->accessEndsAt())->toBeNull()
            ->and($subscription->accessLapsed())->toBeFalse();
    });
});

describe('cancelling', function () {
    it('stops the renewal: open invoices are voided and the subscription keeps its end date', function () {
        $subscription = SubscriptionFactory::new()->active(5)->create();
        $renewal = app(SubscriptionManager::class)->renew($subscription);
        $end = $subscription->current_period_end->toDateString();

        app(SubscriptionManager::class)->cancel($subscription);

        expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Cancelled)
            ->and($subscription->current_period_end->toDateString())->toBe($end)
            ->and($renewal->refresh()->status)->toBe(InvoiceStatus::Void)
            ->and(Tenant::query()->find($subscription->tenant_id)->status)->toBe(TenantStatus::Active);
    });

    it('is undone by paying a new invoice', function () {
        $subscription = SubscriptionFactory::new()->active(5)->cancelled()->create();

        lifecyclePay($subscription);

        expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active)
            ->and($subscription->cancelled_at)->toBeNull();
    });
});

describe('suspension reasons', function () {
    it('keeps the first reason of a school that is already suspended', function () {
        $tenant = TenantFactory::new()->suspended()->create();

        app(TenantLifecycle::class)->suspend($tenant, SuspensionReason::Billing);

        expect($tenant->refresh()->suspended_reason)->toBe(SuspensionReason::Manual);
    });

    it('records a billing suspension and clears the reason when the school is reactivated', function () {
        $tenant = TenantFactory::new()->create();

        app(TenantLifecycle::class)->suspend($tenant, SuspensionReason::Billing);

        expect($tenant->refresh()->status)->toBe(TenantStatus::Suspended)
            ->and($tenant->suspended_reason)->toBe(SuspensionReason::Billing);

        app(TenantLifecycle::class)->activate($tenant);

        expect($tenant->refresh()->status)->toBe(TenantStatus::Active)
            ->and($tenant->suspended_reason)->toBeNull();
    });

    it('counts a suspension from the artisan command as the provider\'s own', function () {
        $tenant = TenantFactory::new()->create(['slug' => 'sekolah-uji']);

        artisan('tenant:suspend', ['tenant' => 'sekolah-uji'])->assertSuccessful();

        expect($tenant->refresh()->suspended_reason)->toBe(SuspensionReason::Manual);
    });

    it('records a suspension from the console as manual', function () {
        $tenant = TenantFactory::new()->create();
        actingAs(ProviderUserFactory::new()->create(), 'provider');

        $this->post("http://console.localhost/tenants/{$tenant->id}/suspend")->assertRedirect();

        expect($tenant->refresh()->suspended_reason)->toBe(SuspensionReason::Manual);
    });
});

describe('reopening', function () {
    it('reopens a school closed for billing when its first payment is confirmed, and says so once', function () {
        Mail::fake();
        $tenant = TenantFactory::new()->suspendedForBilling()->create(['billing_email' => 'keuangan@sekolah.test']);
        $subscription = lifecycleSubscription($tenant, ['trial_ends_at' => Carbon::today()->subDays(20)]);

        lifecyclePay($subscription);

        expect($tenant->refresh()->status)->toBe(TenantStatus::Active)
            ->and($tenant->suspended_reason)->toBeNull();

        Mail::assertQueued(AccessReopenedMail::class, 1);
    });

    it('does not reopen a school the provider suspended by hand', function () {
        Mail::fake();
        $tenant = TenantFactory::new()->suspended()->create(['billing_email' => 'keuangan@sekolah.test']);
        $subscription = lifecycleSubscription($tenant);

        lifecyclePay($subscription);

        expect($tenant->refresh()->status)->toBe(TenantStatus::Suspended)
            ->and($tenant->suspended_reason)->toBe(SuspensionReason::Manual)
            ->and($subscription->refresh()->status)->toBe(SubscriptionStatus::Active);

        Mail::assertNotQueued(AccessReopenedMail::class);
    });

    it('does not reopen again when the same payment is confirmed twice', function () {
        Mail::fake();
        $tenant = TenantFactory::new()->suspendedForBilling()->create(['billing_email' => 'keuangan@sekolah.test']);
        $subscription = lifecycleSubscription($tenant);
        $invoice = app(SubscriptionManager::class)->activate($subscription, $subscription->plan->key, BillingCycle::Monthly);
        $payment = app(PaymentGateway::class)->initiate($invoice);
        $outcome = PaymentOutcome::paid($invoice->amount, method: 'bank_transfer', reference: 'TRF-1');

        app(SubscriptionManager::class)->settle($payment, $outcome);
        app(TenantLifecycle::class)->suspend($tenant->refresh(), SuspensionReason::Billing);
        app(SubscriptionManager::class)->settle($payment, $outcome);

        // The second confirmation is a no-op, so it cannot reopen the school.
        expect($tenant->refresh()->status)->toBe(TenantStatus::Suspended);
        Mail::assertQueued(AccessReopenedMail::class, 1);
    });

    it('reopens a school closed for billing when the provider extends its trial', function () {
        Mail::fake();
        $tenant = TenantFactory::new()->suspendedForBilling()->create(['billing_email' => 'keuangan@sekolah.test']);
        $subscription = lifecycleSubscription($tenant, ['trial_ends_at' => Carbon::today()->subDays(30)]);

        app(SubscriptionManager::class)->extendTrial($subscription, 14);

        expect($tenant->refresh()->status)->toBe(TenantStatus::Active);
        Mail::assertQueued(AccessReopenedMail::class, 1);
    });

    it('leaves a manual suspension alone when the trial is extended', function () {
        $tenant = TenantFactory::new()->suspended()->create();
        $subscription = lifecycleSubscription($tenant);

        app(SubscriptionManager::class)->extendTrial($subscription, 14);

        expect($tenant->refresh()->status)->toBe(TenantStatus::Suspended);
    });
});

describe('the page a suspended school sees', function () {
    beforeEach(function () {
        config([
            'billing.issuer.name' => 'PT Penyedia Layanan',
            'billing.issuer.email' => 'bantuan@penyedia.test',
            'billing.issuer.whatsapp' => '+62 812-3456-789',
        ]);
    });

    it('names the school and the provider and reveals nothing about billing', function () {
        $tenant = TenantFactory::new()->suspendedForBilling()->create(['name' => 'SMA Nusantara', 'slug' => 'sma-nusantara']);
        $subscription = lifecycleSubscription($tenant);
        $invoice = InvoiceFactory::new()->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'amount' => 987_654,
        ]);

        $response = get('/sma-nusantara/login')->assertForbidden();

        $response->assertSee('SMA Nusantara')
            ->assertSee('PT Penyedia Layanan')
            ->assertSee('bantuan@penyedia.test')
            ->assertSee('https://wa.me/628123456789', false)
            ->assertDontSee($invoice->number)
            ->assertDontSee('987.654')
            ->assertDontSee('987654')
            ->assertDontSee('Rp')
            ->assertDontSee('langganan', false)
            ->assertDontSee('tagihan', false)
            ->assertHeader('X-Robots-Tag', 'noindex');

        expect($response->headers->get('Cache-Control'))->toContain('no-store');
    });

    it('is the same page whatever the reason for the suspension', function () {
        $billing = TenantFactory::new()->suspendedForBilling()->create(['name' => 'Sekolah Satu', 'slug' => 'sekolah-satu']);
        $manual = TenantFactory::new()->suspended()->create(['name' => 'Sekolah Dua', 'slug' => 'sekolah-dua']);

        $first = get('/sekolah-satu/login')->assertForbidden()->getContent();
        $second = get('/sekolah-dua/login')->assertForbidden()->getContent();

        expect(str_replace('Sekolah Satu', 'X', (string) $first))->toBe(str_replace('Sekolah Dua', 'X', (string) $second));
    });

    it('answers a JSON request with a neutral 403', function () {
        TenantFactory::new()->suspendedForBilling()->create(['slug' => 'sekolah-satu']);

        getJson('/sekolah-satu/login')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Akses sekolah ini sedang dihentikan sementara. Hubungi pengelola sekolah Anda atau penyedia layanan.']);
    });

    it('shows the same page to a signed-in session of that school', function () {
        $tenant = TenantFactory::new()->create(['name' => 'SMA Nusantara', 'slug' => 'sma-nusantara']);

        app(TenantLifecycle::class)->suspend($tenant, SuspensionReason::Billing);

        get('/sma-nusantara/login')->assertForbidden()->assertSee('SMA Nusantara');

        app(TenantLifecycle::class)->activate($tenant);

        get('/sma-nusantara/login')->assertOk();
    });
});

describe('background work of a suspended school', function () {
    it('sends no WhatsApp message and closes it as not sent', function () {
        Http::fake();
        Http::preventStrayRequests();
        $tenant = TenantFactory::new()->suspendedForBilling()->create();

        $result = app(TenantContext::class)->run($tenant->id, fn () => app(WhatsappChannel::class)->sendText('628123456789', 'Halo'));

        expect($result->sent)->toBeFalse()
            ->and($result->unavailable)->toBeTrue()
            ->and($result->retryable)->toBeFalse()
            ->and($result->throttled)->toBeFalse()
            ->and($result->error)->toContain('dihentikan sementara');

        Http::assertNothingSent();
    });
});

describe('billing exemption', function () {
    it('leaves an exempt school out of the revenue figures and the attention list', function () {
        $plan = PlanFactory::new()->create(['price_monthly' => 100_000, 'price_yearly' => 1_000_000]);
        $paying = SubscriptionFactory::new()->forPlan($plan->id)->forTenant(TenantFactory::new()->create()->id)->active(30)->create();
        $exempt = SubscriptionFactory::new()->forPlan($plan->id)->forTenant(TenantFactory::new()->billingExempt()->create()->id)->active(2)->create();

        $summary = app(BillingSummary::class);

        expect($summary->summary()['mrr'])->toBe(100_000)
            ->and($summary->summary()['activeSubscriptions'])->toBe(1)
            ->and($summary->needsAttention()->pluck('id')->all())->not->toContain($exempt->id, $paying->id);
    });

    it('is switched on and off by the provider in the console', function () {
        $tenant = TenantFactory::new()->create();
        actingAs(ProviderUserFactory::new()->create(), 'provider');

        put("http://console.localhost/tenants/{$tenant->id}/billing-exempt", ['exempt' => true])->assertRedirect();
        expect($tenant->refresh()->billing_exempt)->toBeTrue();

        put("http://console.localhost/tenants/{$tenant->id}/billing-exempt", ['exempt' => false])->assertRedirect();
        expect($tenant->refresh()->billing_exempt)->toBeFalse();

        put("http://console.localhost/tenants/{$tenant->id}/billing-exempt", [])->assertSessionHasErrors('exempt');
    });
});
