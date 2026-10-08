<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\DTOs\BillingOverview;
use Modules\Platform\App\Contracts\DTOs\PlanChangeOutcome;
use Modules\Platform\App\Contracts\DTOs\PlanOffer;
use Modules\Platform\App\Contracts\Exceptions\BillingActionRefusedException;
use Modules\Platform\App\Contracts\Exceptions\TenantNotSetException;
use Modules\Platform\App\Contracts\TenantBilling;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\UsageMeters;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceKind;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Payment;
use Modules\Platform\App\Domain\Models\PaymentStatus;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\InvoiceFactory;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\SubscriptionFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/**
 * The school's own door to its subscription (fase 17, tahap 8): the
 * TenantBilling contract, who may use it, and the actions behind the
 * account panels.
 */
function billingSchool(string $slug = 'sekolah-uji', string $role = 'admin-sekolah', array $tenant = []): Tenant
{
    $school = TenantFactory::new()->create(['slug' => $slug, ...$tenant]);
    $user = UserFactory::new()->forTenant($school->id)->create(['email' => "{$role}@{$slug}.test"]);

    app(TenantContext::class)->run($school->id, fn () => $user->assignTenantRole($role));

    actingAs($user);

    return $school;
}

function billingInertiaVersion(): string
{
    return (string) app(HandleInertiaRequests::class)->version(request());
}

/**
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return T
 */
function asBillingSchool(Tenant $school, Closure $callback): mixed
{
    return app(TenantContext::class)->run($school->id, $callback);
}

function billingPlan(string $key, int $monthly, array $state = []): Plan
{
    return PlanFactory::new()->create([
        'key' => $key,
        'name' => ucfirst($key),
        'price_monthly' => $monthly,
        'price_yearly' => $monthly * 10,
        'modules' => ['core', 'identity'],
        ...$state,
    ]);
}

/** The refusal a call ends in, or null when it went through. */
function billingRefusal(Closure $call): ?BillingActionRefusedException
{
    try {
        $call();
    } catch (BillingActionRefusedException $exception) {
        return $exception;
    }

    return null;
}

beforeEach(function () {
    config([
        'billing.issuer.bank' => ['name' => 'BCA', 'account' => '1234567890', 'holder' => 'PT SIMAS'],
    ]);
});

describe('the overview', function () {
    it('describes an active subscription with its plan, dates, modules and usage', function () {
        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
        $school = billingSchool();
        $plan = billingPlan('starter', 100_000, ['limits' => ['storage_mb' => 100]]);
        SubscriptionFactory::new()->forTenant($school->id)->forPlan($plan->id)->active(20)->create();

        $overview = asBillingSchool($school, fn () => app(TenantBilling::class)->overview());

        expect($overview->state)->toBe('active')
            ->and($overview->planKey)->toBe('starter')
            ->and($overview->cycle)->toBe('monthly')
            ->and($overview->price)->toBe(100_000)
            ->and($overview->periodEnd)->toBe('2026-10-28')
            ->and($overview->accessEndsOn)->toBe('2026-11-04')
            ->and(collect($overview->modules)->pluck('key')->all())->toContain('core')
            ->and(collect($overview->usage)->pluck('key')->all())->toContain('storage')
            ->and($overview->canSubscribe)->toBeFalse()
            ->and($overview->canChangePlan)->toBeTrue();
    });

    it('says there is no subscription yet', function () {
        $school = billingSchool();

        $overview = asBillingSchool($school, fn () => app(TenantBilling::class)->overview());

        expect($overview->state)->toBe(BillingOverview::STATE_NONE)
            ->and($overview->planName)->toBeNull()
            ->and($overview->canSubscribe)->toBeFalse()
            ->and($overview->canChangePlan)->toBeFalse();
    });

    it('offers a trial the choice of a plan, and an exempt school nothing', function () {
        $trial = billingSchool('sekolah-a');
        SubscriptionFactory::new()->forTenant($trial->id)->trialEndingIn(5)->create();
        $exempt = billingSchool('sekolah-b', 'admin-sekolah', ['billing_exempt' => true]);
        SubscriptionFactory::new()->forTenant($exempt->id)->active(20)->create();

        $trialOverview = asBillingSchool($trial, fn () => app(TenantBilling::class)->overview());
        $exemptOverview = asBillingSchool($exempt, fn () => app(TenantBilling::class)->overview());

        expect($trialOverview->state)->toBe('trial')
            ->and($trialOverview->canSubscribe)->toBeTrue()
            ->and($trialOverview->canChangePlan)->toBeTrue()
            ->and($exemptOverview->state)->toBe(BillingOverview::STATE_EXEMPT)
            ->and($exemptOverview->canSubscribe)->toBeFalse()
            ->and($exemptOverview->canChangePlan)->toBeFalse();
    });

    it('names a downgrade that is scheduled', function () {
        $school = billingSchool();
        $pro = billingPlan('pro', 300_000);
        $starter = billingPlan('starter', 100_000);
        SubscriptionFactory::new()->forTenant($school->id)->forPlan($pro->id)->active(20)->create(['scheduled_plan_id' => $starter->id]);

        $overview = asBillingSchool($school, fn () => app(TenantBilling::class)->overview());

        expect($overview->scheduledPlanKey)->toBe('starter')
            ->and($overview->scheduledPlanName)->toBe('Starter');
    });

    it('fails closed without a school', function () {
        expect(fn () => app(TenantBilling::class)->overview())->toThrow(TenantNotSetException::class)
            ->and(fn () => app(TenantBilling::class)->invoices())->toThrow(TenantNotSetException::class)
            ->and(fn () => app(TenantBilling::class)->offers())->toThrow(TenantNotSetException::class)
            ->and(fn () => app(TenantBilling::class)->startPayment('INV-2610-0001'))->toThrow(TenantNotSetException::class);
    });
});

describe('invoices and isolation between schools', function () {
    it('lists only the school\'s own invoices, newest first', function () {
        $school = billingSchool('sekolah-a');
        $other = TenantFactory::new()->create(['slug' => 'sekolah-b']);
        $mine = SubscriptionFactory::new()->forTenant($school->id)->active(20)->create();
        $theirs = SubscriptionFactory::new()->forTenant($other->id)->active(20)->create();
        $first = InvoiceFactory::new()->create(['tenant_id' => $school->id, 'subscription_id' => $mine->id, 'plan_id' => $mine->plan_id, 'number' => 'INV-2609-0001']);
        $second = InvoiceFactory::new()->create(['tenant_id' => $school->id, 'subscription_id' => $mine->id, 'plan_id' => $mine->plan_id, 'number' => 'INV-2610-0001']);
        InvoiceFactory::new()->create(['tenant_id' => $other->id, 'subscription_id' => $theirs->id, 'plan_id' => $theirs->plan_id, 'number' => 'INV-2610-0099']);

        $numbers = collect(asBillingSchool($school, fn () => app(TenantBilling::class)->invoices()))->pluck('number')->all();

        expect($numbers)->toBe([$second->number, $first->number]);
    });

    it('treats another school\'s invoice as not found, in every action', function () {
        $school = billingSchool('sekolah-a');
        $other = TenantFactory::new()->create(['slug' => 'sekolah-b']);
        $theirs = SubscriptionFactory::new()->forTenant($other->id)->active(20)->create();
        $invoice = InvoiceFactory::new()->create(['tenant_id' => $other->id, 'subscription_id' => $theirs->id, 'plan_id' => $theirs->plan_id, 'number' => 'INV-2610-0099']);
        $billing = fn () => app(TenantBilling::class);

        $refusals = [
            billingRefusal(fn () => asBillingSchool($school, fn () => $billing()->startPayment($invoice->number))),
            billingRefusal(fn () => asBillingSchool($school, fn () => $billing()->reportTransfer($invoice->number, '2026-10-01', 'BCA', 'Budi'))),
            billingRefusal(fn () => asBillingSchool($school, fn () => $billing()->invoicePdf($invoice->number))),
            billingRefusal(fn () => asBillingSchool($school, fn () => $billing()->startPayment('INV-9999-9999'))),
        ];

        foreach ($refusals as $refusal) {
            expect($refusal)->not->toBeNull()->and($refusal->notFound)->toBeTrue();
        }

        expect(Payment::query()->count())->toBe(0);
    });

    it('flags an unpaid invoice as overdue past its due date and shows instructions while a payment waits', function () {
        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
        $school = billingSchool();
        $subscription = SubscriptionFactory::new()->forTenant($school->id)->active(20)->create();
        $invoice = InvoiceFactory::new()->create([
            'tenant_id' => $school->id, 'subscription_id' => $subscription->id, 'plan_id' => $subscription->plan_id,
            'due_at' => Carbon::parse('2026-10-01'), 'amount' => 123_000,
        ]);

        $before = asBillingSchool($school, fn () => app(TenantBilling::class)->invoices())[0];
        asBillingSchool($school, fn () => app(TenantBilling::class)->startPayment($invoice->number));
        $after = asBillingSchool($school, fn () => app(TenantBilling::class)->invoices())[0];

        expect($before->status)->toBe('overdue')
            ->and($before->payable)->toBeTrue()
            ->and($before->instructions)->toBeNull()
            ->and($after->instructions->amount)->toBe(123_000)
            ->and($after->instructions->bankName)->toBe('BCA')
            ->and($after->instructions->bankAccount)->toBe('1234567890')
            ->and($after->instructions->note)->toContain($invoice->number);
    });
});

describe('subscribing', function () {
    it('issues the invoice for a public plan and cycle while on a trial', function () {
        $school = billingSchool();
        $plan = billingPlan('starter', 100_000);
        SubscriptionFactory::new()->forTenant($school->id)->trialEndingIn(5)->create();

        $invoice = asBillingSchool($school, fn () => app(TenantBilling::class)->subscribe('starter', 'yearly'));

        expect($invoice->kind)->toBe('activation')
            ->and($invoice->status)->toBe('unpaid')
            ->and($invoice->amount)->toBe(1_000_000)
            ->and($invoice->planName)->toBe('Starter')
            ->and(Invoice::query()->where('tenant_id', $school->id)->sole()->plan_id)->toBe($plan->id);
    });

    it('lets a cancelled subscription subscribe again', function () {
        $school = billingSchool();
        billingPlan('starter', 100_000);
        SubscriptionFactory::new()->forTenant($school->id)->active(5)->cancelled()->create();

        $invoice = asBillingSchool($school, fn () => app(TenantBilling::class)->subscribe('starter', 'monthly'));

        expect($invoice->status)->toBe('unpaid');
    });

    it('refuses a private plan, an unknown cycle, a running subscription and an exempt school', function () {
        $school = billingSchool('sekolah-a');
        billingPlan('starter', 100_000);
        PlanFactory::new()->private()->create(['key' => 'khusus']);
        SubscriptionFactory::new()->forTenant($school->id)->trialEndingIn(5)->create();
        $active = billingSchool('sekolah-b');
        SubscriptionFactory::new()->forTenant($active->id)->active(20)->create();
        $exempt = billingSchool('sekolah-c', 'admin-sekolah', ['billing_exempt' => true]);
        SubscriptionFactory::new()->forTenant($exempt->id)->trialEndingIn(5)->create();

        $refusals = [
            billingRefusal(fn () => asBillingSchool($school, fn () => app(TenantBilling::class)->subscribe('khusus', 'monthly'))),
            billingRefusal(fn () => asBillingSchool($school, fn () => app(TenantBilling::class)->subscribe('tidak-ada', 'monthly'))),
            billingRefusal(fn () => asBillingSchool($school, fn () => app(TenantBilling::class)->subscribe('starter', 'weekly'))),
            billingRefusal(fn () => asBillingSchool($active, fn () => app(TenantBilling::class)->subscribe('starter', 'monthly'))),
            billingRefusal(fn () => asBillingSchool($exempt, fn () => app(TenantBilling::class)->subscribe('starter', 'monthly'))),
        ];

        foreach ($refusals as $refusal) {
            expect($refusal)->not->toBeNull()->and($refusal->notFound)->toBeFalse();
        }

        expect(Invoice::query()->count())->toBe(0);
    });

    it('refuses a school with no subscription at all', function () {
        $school = billingSchool();
        billingPlan('starter', 100_000);

        $refusal = billingRefusal(fn () => asBillingSchool($school, fn () => app(TenantBilling::class)->subscribe('starter', 'monthly')));

        expect($refusal?->getMessage())->toContain('belum punya langganan');
    });
});

describe('paying', function () {
    it('starts one payment per invoice and hands out the account to transfer to', function () {
        $school = billingSchool();
        $subscription = SubscriptionFactory::new()->forTenant($school->id)->trialEndingIn(5)->create();
        $invoice = InvoiceFactory::new()->create(['tenant_id' => $school->id, 'subscription_id' => $subscription->id, 'plan_id' => $subscription->plan_id, 'amount' => 150_000]);

        $first = asBillingSchool($school, fn () => app(TenantBilling::class)->startPayment($invoice->number));
        $second = asBillingSchool($school, fn () => app(TenantBilling::class)->startPayment($invoice->number));

        expect($first->amount)->toBe(150_000)
            ->and($first->bankAccount)->toBe('1234567890')
            ->and($first->accountHolder)->toBe('PT SIMAS')
            ->and($second->invoiceNumber)->toBe($first->invoiceNumber)
            ->and(Payment::query()->where('invoice_id', $invoice->id)->count())->toBe(1)
            ->and(Payment::query()->sole()->status)->toBe(PaymentStatus::Pending);
    });

    it('does not take payment for an invoice that is paid or void', function () {
        $school = billingSchool();
        $subscription = SubscriptionFactory::new()->forTenant($school->id)->active(20)->create();
        $paid = InvoiceFactory::new()->paid()->create(['tenant_id' => $school->id, 'subscription_id' => $subscription->id, 'plan_id' => $subscription->plan_id]);
        $void = InvoiceFactory::new()->create(['tenant_id' => $school->id, 'subscription_id' => $subscription->id, 'plan_id' => $subscription->plan_id, 'status' => InvoiceStatus::Void]);

        expect(billingRefusal(fn () => asBillingSchool($school, fn () => app(TenantBilling::class)->startPayment($paid->number))))->not->toBeNull()
            ->and(billingRefusal(fn () => asBillingSchool($school, fn () => app(TenantBilling::class)->startPayment($void->number))))->not->toBeNull()
            ->and(Payment::query()->count())->toBe(0);
    });

    it('records a reported transfer on the waiting payment and keeps it pending', function () {
        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
        $school = billingSchool();
        $subscription = SubscriptionFactory::new()->forTenant($school->id)->active(20)->create();
        $invoice = InvoiceFactory::new()->create(['tenant_id' => $school->id, 'subscription_id' => $subscription->id, 'plan_id' => $subscription->plan_id]);

        asBillingSchool($school, fn () => app(TenantBilling::class)->reportTransfer($invoice->number, '2026-10-07', ' BCA ', 'Budi Santoso', 'TRF-77'));

        $payment = Payment::query()->sole();
        $summary = asBillingSchool($school, fn () => app(TenantBilling::class)->invoices())[0];

        expect($payment->status)->toBe(PaymentStatus::Pending)
            ->and($payment->meta['reported'])->toMatchArray(['transferredOn' => '2026-10-07', 'bank' => 'BCA', 'senderName' => 'Budi Santoso', 'reference' => 'TRF-77'])
            ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Unpaid)
            ->and($summary->instructions->reportedTransfer)->toBe(['transferredOn' => '2026-10-07', 'bank' => 'BCA', 'senderName' => 'Budi Santoso', 'reference' => 'TRF-77']);
    });

    it('refuses a transfer dated in the future or not a date', function () {
        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
        $school = billingSchool();
        $subscription = SubscriptionFactory::new()->forTenant($school->id)->active(20)->create();
        $invoice = InvoiceFactory::new()->create(['tenant_id' => $school->id, 'subscription_id' => $subscription->id, 'plan_id' => $subscription->plan_id]);

        foreach (['2026-10-09', '2026-02-31', 'kemarin'] as $date) {
            expect(billingRefusal(fn () => asBillingSchool($school, fn () => app(TenantBilling::class)->reportTransfer($invoice->number, $date, 'BCA', 'Budi'))))
                ->not->toBeNull();
        }

        expect(Payment::query()->count())->toBe(0);
    });

    it('renders the invoice as a PDF', function () {
        $school = billingSchool();
        $subscription = SubscriptionFactory::new()->forTenant($school->id)->active(20)->create();
        $invoice = InvoiceFactory::new()->create(['tenant_id' => $school->id, 'subscription_id' => $subscription->id, 'plan_id' => $subscription->plan_id, 'number' => 'INV-2610-0042']);

        $file = asBillingSchool($school, fn () => app(TenantBilling::class)->invoicePdf($invoice->number));

        expect(substr($file->contents, 0, 4))->toBe('%PDF')
            ->and($file->filename)->toContain('INV-2610-0042');
    });
});

describe('changing plan', function () {
    it('switches at once during a trial', function () {
        $school = billingSchool();
        billingPlan('starter', 100_000);
        $pro = billingPlan('pro', 300_000);
        SubscriptionFactory::new()->forTenant($school->id)->trialEndingIn(5)->create();

        $outcome = asBillingSchool($school, fn () => app(TenantBilling::class)->changePlan('pro'));

        expect($outcome->kind)->toBe(PlanChangeOutcome::IMMEDIATE)
            ->and(Subscription::query()->where('tenant_id', $school->id)->sole()->plan_id)->toBe($pro->id);
    });

    it('answers a dearer plan with an invoice for the prorated difference and keeps the current plan', function () {
        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
        $school = billingSchool();
        $starter = billingPlan('starter', 100_000);
        billingPlan('pro', 400_000);
        $subscription = SubscriptionFactory::new()->forTenant($school->id)->forPlan($starter->id)->active(15)->create();

        $outcome = asBillingSchool($school, fn () => app(TenantBilling::class)->changePlan('pro'));

        expect($outcome->kind)->toBe(PlanChangeOutcome::UPGRADE)
            ->and($outcome->invoice->kind)->toBe(InvoiceKind::Upgrade->value)
            ->and($outcome->invoice->amount)->toBeGreaterThan(0)
            ->and($subscription->refresh()->plan_id)->toBe($starter->id);
    });

    it('schedules a cheaper plan for the end of the paid period, and the school can take it back', function () {
        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
        $school = billingSchool();
        $starter = billingPlan('starter', 100_000);
        $pro = billingPlan('pro', 400_000);
        $subscription = SubscriptionFactory::new()->forTenant($school->id)->forPlan($pro->id)->active(15)->create();

        $outcome = asBillingSchool($school, fn () => app(TenantBilling::class)->changePlan('starter'));

        expect($outcome->kind)->toBe(PlanChangeOutcome::SCHEDULED)
            ->and($outcome->scheduledPlanName)->toBe('Starter')
            ->and($outcome->takesEffectOn)->toBe($subscription->current_period_end->toDateString())
            ->and($subscription->refresh()->scheduled_plan_id)->toBe($starter->id)
            ->and($subscription->plan_id)->toBe($pro->id);

        asBillingSchool($school, fn () => app(TenantBilling::class)->cancelScheduledChange());

        expect($subscription->refresh()->scheduled_plan_id)->toBeNull();
    });

    it('refuses a private plan, the plan already held, and a lapsed subscription, in words a school can read', function () {
        $school = billingSchool('sekolah-a');
        $starter = billingPlan('starter', 100_000);
        PlanFactory::new()->private()->create(['key' => 'khusus']);
        SubscriptionFactory::new()->forTenant($school->id)->forPlan($starter->id)->active(15)->create();
        $lapsed = billingSchool('sekolah-b');
        SubscriptionFactory::new()->forTenant($lapsed->id)->forPlan($starter->id)->active(-3)->create();

        $private = billingRefusal(fn () => asBillingSchool($school, fn () => app(TenantBilling::class)->changePlan('khusus')));
        $same = billingRefusal(fn () => asBillingSchool($school, fn () => app(TenantBilling::class)->changePlan('starter')));
        $late = billingRefusal(fn () => asBillingSchool($lapsed, fn () => app(TenantBilling::class)->changePlan('starter')));

        expect($private?->getMessage())->toBe('Paket tidak tersedia.')
            ->and($same?->getMessage())->toBe('Sekolah sudah memakai paket ini.')
            ->and($late?->getMessage())->toContain('Perpanjang');

        foreach ([$private, $same, $late] as $refusal) {
            expect($refusal->getMessage())->not->toContain('plan');
        }
    });

    it('changes the cycle for the next invoice, but not for a cancelled subscription', function () {
        $school = billingSchool('sekolah-a');
        $subscription = SubscriptionFactory::new()->forTenant($school->id)->active(15)->create();
        $cancelled = billingSchool('sekolah-b');
        SubscriptionFactory::new()->forTenant($cancelled->id)->active(15)->cancelled()->create();

        asBillingSchool($school, fn () => app(TenantBilling::class)->changeCycle('yearly'));

        expect($subscription->refresh()->billing_cycle->value)->toBe('yearly')
            ->and(billingRefusal(fn () => asBillingSchool($school, fn () => app(TenantBilling::class)->changeCycle('daily'))))->not->toBeNull()
            ->and(billingRefusal(fn () => asBillingSchool($cancelled, fn () => app(TenantBilling::class)->changeCycle('yearly'))))->not->toBeNull();
    });
});

describe('the plans on offer', function () {
    it('lists the public plans with the direction from the current one, what would be lost and what would be over', function () {
        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
        app(UsageMeters::class)->register('platform', 'probe', 'Contoh pemakaian', 'item', fn (): int => 500, 'probe');

        $school = billingSchool();
        $pro = billingPlan('pro', 400_000, ['modules' => ['core', 'identity', 'attendance'], 'limits' => ['probe' => 1000]]);
        billingPlan('starter', 100_000, ['modules' => ['core', 'identity'], 'limits' => ['probe' => 300]]);
        billingPlan('besar', 900_000, ['modules' => ['core', 'identity', 'attendance', 'ppdb']]);
        PlanFactory::new()->private()->create(['key' => 'khusus']);
        PlanFactory::new()->archived()->create(['key' => 'lama']);
        SubscriptionFactory::new()->forTenant($school->id)->forPlan($pro->id)->active(15)->create();

        $offers = collect(asBillingSchool($school, fn () => app(TenantBilling::class)->offers()))->keyBy('key');

        expect($offers->keys()->sort()->values()->all())->toBe(['besar', 'pro', 'starter'])
            ->and($offers['pro']->direction)->toBe(PlanOffer::CURRENT)
            ->and($offers['besar']->direction)->toBe(PlanOffer::UPGRADE)
            ->and($offers['starter']->direction)->toBe(PlanOffer::DOWNGRADE)
            ->and($offers['starter']->modulesLost)->toHaveCount(1)
            ->and($offers['starter']->overLimits)->toBe([['label' => 'Contoh pemakaian', 'unit' => 'item', 'used' => 500, 'limit' => 300]])
            ->and($offers['besar']->modulesLost)->toBe([])
            ->and($offers['pro']->modulesLost)->toBe([])
            ->and($offers['pro']->overLimits)->toBe([]);
    });

    it('has no direction for a school without a subscription', function () {
        $school = billingSchool();
        billingPlan('starter', 100_000);

        $offers = asBillingSchool($school, fn () => app(TenantBilling::class)->offers());

        expect($offers)->toHaveCount(1)->and($offers[0]->direction)->toBeNull();
    });
});

describe('who may use it', function () {
    beforeEach(function () {
        Route::middleware('web')->get('/tenant-billing-probe', fn (): Response => Inertia::render('welcome'));
    });

    /** Ask for the `billing` prop the way the account panels do: a partial reload. */
    function billingPropOf(string $slug): array
    {
        $response = get(school($slug, '/tenant-billing-probe'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => billingInertiaVersion(),
            'X-Inertia-Partial-Component' => 'welcome',
            'X-Inertia-Partial-Data' => 'billing',
        ]);

        return (array) $response->json('props');
    }

    it('keeps the billing prop out of an ordinary page and sends it on a partial reload', function () {
        $school = billingSchool();
        SubscriptionFactory::new()->forTenant($school->id)->active(20)->create();

        get(school($school->slug, '/tenant-billing-probe'), ['X-Inertia' => 'true', 'X-Inertia-Version' => billingInertiaVersion()])
            ->assertOk()
            ->assertJsonMissingPath('props.billing');

        $props = billingPropOf($school->slug);

        expect($props['billing']['overview']['state'])->toBe('active')
            ->and($props['billing']['canPay'])->toBeTrue()
            ->and($props['billing']['urls']['subscribe'])->toContain('/langganan/berlangganan')
            ->and($props['billing'])->toHaveKeys(['overview', 'invoices', 'offers', 'urls']);
    });

    it('gives the school admin both billing permissions and a teacher neither', function () {
        $admin = billingSchool('sekolah-a');
        $adminUser = auth()->user();
        $teacher = billingSchool('sekolah-b', 'guru');
        $teacherUser = auth()->user();

        $can = fn (Tenant $school, $user, string $permission): bool => asBillingSchool($school, fn () => Gate::forUser($user)->allows($permission));

        expect($can($admin, $adminUser, 'platform.billing.view'))->toBeTrue()
            ->and($can($admin, $adminUser, 'platform.billing.pay'))->toBeTrue()
            ->and($can($teacher, $teacherUser, 'platform.billing.view'))->toBeFalse()
            ->and($can($teacher, $teacherUser, 'platform.billing.pay'))->toBeFalse();
    });

    it('sends nothing to a user who may not see billing, even when asked', function () {
        $school = billingSchool('sekolah-uji', 'guru');
        SubscriptionFactory::new()->forTenant($school->id)->active(20)->create();

        expect(billingPropOf($school->slug)['billing'] ?? null)->toBeNull();
    });

    it('leaves the offers, the links and the payment instructions out for a user who may only look', function () {
        $school = billingSchool('sekolah-uji', 'staf-tu');
        $user = auth()->user();
        app(TenantContext::class)->run($school->id, fn () => $user->givePermissionTo('platform.billing.view'));
        $subscription = SubscriptionFactory::new()->forTenant($school->id)->trialEndingIn(5)->create();
        $invoice = InvoiceFactory::new()->create(['tenant_id' => $school->id, 'subscription_id' => $subscription->id, 'plan_id' => $subscription->plan_id]);
        asBillingSchool($school, fn () => app(TenantBilling::class)->startPayment($invoice->number));
        billingPlan('starter', 100_000);

        $billing = billingPropOf($school->slug)['billing'];

        expect($billing['canPay'])->toBeFalse()
            ->and($billing['urls'])->toBeNull()
            ->and($billing['offers'])->toBe([])
            ->and($billing['overview']['canSubscribe'])->toBeFalse()
            ->and($billing['invoices'][0]['payable'])->toBeFalse()
            ->and($billing['invoices'][0]['instructions'])->toBeNull()
            ->and($billing['invoices'][0]['urls'])->toBeNull();
    });

    it('refuses every action to a user without the pay permission and to a guest', function () {
        $school = billingSchool('sekolah-uji', 'guru');
        $subscription = SubscriptionFactory::new()->forTenant($school->id)->trialEndingIn(5)->create();
        $invoice = InvoiceFactory::new()->create(['tenant_id' => $school->id, 'subscription_id' => $subscription->id, 'plan_id' => $subscription->plan_id]);

        post(school($school->slug, '/langganan/berlangganan'), ['plan' => 'x', 'cycle' => 'monthly'])->assertForbidden();
        post(school($school->slug, '/langganan/paket'), ['plan' => 'x'])->assertForbidden();
        delete(school($school->slug, '/langganan/paket/jadwal'))->assertForbidden();
        put(school($school->slug, '/langganan/siklus'), ['cycle' => 'yearly'])->assertForbidden();
        post(school($school->slug, "/langganan/invoice/{$invoice->number}/bayar"))->assertForbidden();
        post(school($school->slug, "/langganan/invoice/{$invoice->number}/transfer"), [])->assertForbidden();
        get(school($school->slug, "/langganan/invoice/{$invoice->number}/pdf"))->assertForbidden();

        auth()->guard('web')->logout();
        post(school($school->slug, "/langganan/invoice/{$invoice->number}/bayar"))->assertRedirect();
    });
});

describe('the actions behind the panels', function () {
    it('subscribes and shows the invoice number as the message', function () {
        $school = billingSchool();
        billingPlan('starter', 100_000);
        SubscriptionFactory::new()->forTenant($school->id)->trialEndingIn(5)->create();

        post(school($school->slug, '/langganan/berlangganan'), ['plan' => 'starter', 'cycle' => 'monthly'])
            ->assertRedirect()
            ->assertSessionHas('status');

        expect(Invoice::query()->where('tenant_id', $school->id)->count())->toBe(1);
    });

    it('turns a refusal into a form error the panel can show', function () {
        $school = billingSchool();
        SubscriptionFactory::new()->forTenant($school->id)->trialEndingIn(5)->create();

        post(school($school->slug, '/langganan/berlangganan'), ['plan' => 'tidak-ada', 'cycle' => 'monthly'])
            ->assertRedirect()
            ->assertSessionHasErrors(['billing' => 'Paket tidak tersedia.']);
    });

    it('validates the cycle, the plan and the reported transfer', function () {
        $school = billingSchool();
        $subscription = SubscriptionFactory::new()->forTenant($school->id)->trialEndingIn(5)->create();
        $invoice = InvoiceFactory::new()->create(['tenant_id' => $school->id, 'subscription_id' => $subscription->id, 'plan_id' => $subscription->plan_id]);

        post(school($school->slug, '/langganan/berlangganan'), ['plan' => 'starter', 'cycle' => 'weekly'])->assertSessionHasErrors('cycle');
        post(school($school->slug, '/langganan/paket'), [])->assertSessionHasErrors('plan');
        put(school($school->slug, '/langganan/siklus'), ['cycle' => 'daily'])->assertSessionHasErrors('cycle');
        post(school($school->slug, "/langganan/invoice/{$invoice->number}/transfer"), ['transferred_on' => 'kemarin'])
            ->assertSessionHasErrors(['transferred_on', 'bank', 'sender_name']);
    });

    it('starts a payment and takes a reported transfer', function () {
        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
        $school = billingSchool();
        $subscription = SubscriptionFactory::new()->forTenant($school->id)->trialEndingIn(5)->create();
        $invoice = InvoiceFactory::new()->create(['tenant_id' => $school->id, 'subscription_id' => $subscription->id, 'plan_id' => $subscription->plan_id]);

        post(school($school->slug, "/langganan/invoice/{$invoice->number}/bayar"))->assertRedirect()->assertSessionHasNoErrors();
        post(school($school->slug, "/langganan/invoice/{$invoice->number}/transfer"), [
            'transferred_on' => '2026-10-07', 'bank' => 'BCA', 'sender_name' => 'Budi', 'reference' => 'TRF-1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $payment = Payment::query()->sole();

        expect($payment->status)->toBe(PaymentStatus::Pending)
            ->and($payment->meta['reported']['reference'])->toBe('TRF-1');
    });

    it('answers another school\'s invoice with a 404 and downloads its own as a PDF', function () {
        $school = billingSchool('sekolah-a');
        $other = TenantFactory::new()->create(['slug' => 'sekolah-b']);
        $mine = SubscriptionFactory::new()->forTenant($school->id)->active(20)->create();
        $theirs = SubscriptionFactory::new()->forTenant($other->id)->active(20)->create();
        $own = InvoiceFactory::new()->create(['tenant_id' => $school->id, 'subscription_id' => $mine->id, 'plan_id' => $mine->plan_id, 'number' => 'INV-2610-0001']);
        $foreign = InvoiceFactory::new()->create(['tenant_id' => $other->id, 'subscription_id' => $theirs->id, 'plan_id' => $theirs->plan_id, 'number' => 'INV-2610-0002']);

        post(school('sekolah-a', "/langganan/invoice/{$foreign->number}/bayar"))->assertNotFound();
        get(school('sekolah-a', "/langganan/invoice/{$foreign->number}/pdf"))->assertNotFound();

        $response = get(school('sekolah-a', "/langganan/invoice/{$own->number}/pdf"))->assertOk();

        expect($response->headers->get('Content-Type'))->toBe('application/pdf')
            ->and($response->headers->get('Content-Disposition'))->toContain('INV-2610-0001')
            ->and(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
    });

    it('changes plan, schedules a downgrade and drops the schedule', function () {
        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
        $school = billingSchool();
        $starter = billingPlan('starter', 100_000);
        $pro = billingPlan('pro', 400_000);
        $subscription = SubscriptionFactory::new()->forTenant($school->id)->forPlan($pro->id)->active(15)->create();

        post(school($school->slug, '/langganan/paket'), ['plan' => 'starter'])->assertRedirect()->assertSessionHas('status');
        expect($subscription->refresh()->scheduled_plan_id)->toBe($starter->id);

        delete(school($school->slug, '/langganan/paket/jadwal'))->assertRedirect()->assertSessionHas('status');
        expect($subscription->refresh()->scheduled_plan_id)->toBeNull();

        put(school($school->slug, '/langganan/siklus'), ['cycle' => 'yearly'])->assertRedirect()->assertSessionHas('status');
        expect($subscription->refresh()->billing_cycle->value)->toBe('yearly');
    });

    it('leaves a console provider who is not a school user out of it', function () {
        actingAs(ProviderUserFactory::new()->create(), 'provider');

        post('http://localhost/langganan/berlangganan', ['plan' => 'x', 'cycle' => 'monthly'])->assertForbidden();
    });
});

describe('the console sees what a school reported', function () {
    it('shows the reported transfer on the provider\'s invoice list', function () {
        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
        $school = TenantFactory::new()->create();
        $subscription = SubscriptionFactory::new()->forTenant($school->id)->active(20)->create();
        $invoice = InvoiceFactory::new()->create(['tenant_id' => $school->id, 'subscription_id' => $subscription->id, 'plan_id' => $subscription->plan_id]);

        asBillingSchool($school, fn () => app(TenantBilling::class)->reportTransfer($invoice->number, '2026-10-07', 'BCA', 'Budi Santoso', 'TRF-9'));

        actingAs(ProviderUserFactory::new()->create(), 'provider');

        get('http://console.localhost/billing/invoices')->assertInertia(fn ($page) => $page
            ->where('invoices.data.0.reportedTransfer.senderName', 'Budi Santoso')
            ->where('invoices.data.0.reportedTransfer.reference', 'TRF-9'));
    });

    it('marks the subscription active only once the provider confirms', function () {
        $school = TenantFactory::new()->create();
        $subscription = SubscriptionFactory::new()->forTenant($school->id)->trialEndingIn(5)->create();

        $summary = asBillingSchool($school, fn () => app(TenantBilling::class)->subscribe($subscription->plan->key, 'monthly'));
        asBillingSchool($school, fn () => app(TenantBilling::class)->startPayment($summary->number));

        expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Trial);
    });
});
