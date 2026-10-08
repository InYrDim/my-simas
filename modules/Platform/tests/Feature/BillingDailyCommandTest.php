<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\PendingCommand;
use Modules\Platform\App\Domain\Models\BillingNotice;
use Modules\Platform\App\Domain\Models\BillingNoticeKind;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceKind;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\PaymentStatus;
use Modules\Platform\App\Domain\Models\ProviderSetting;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;
use Modules\Platform\App\Domain\Models\SuspensionReason;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantStatus;
use Modules\Platform\App\Domain\Support\BillingClock;
use Modules\Platform\App\Infrastructure\Billing\Daily\BillingDaily;
use Modules\Platform\App\Infrastructure\Mail\AccessStoppedMail;
use Modules\Platform\App\Infrastructure\Mail\DueReminderMail;
use Modules\Platform\App\Infrastructure\Mail\InvoiceIssuedMail;
use Modules\Platform\App\Infrastructure\Mail\OverdueReminderMail;
use Modules\Platform\App\Infrastructure\Mail\TrialEndingMail;
use Modules\Platform\Database\Factories\InvoiceFactory;
use Modules\Platform\Database\Factories\PaymentFactory;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\SubscriptionFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

/**
 * billing:daily (fase 17, tahap 6). Every scenario runs on a fixed billing
 * day, 2026-10-08, through --date, so none of them depends on the clock.
 */
const DAILY_DAY = '2026-10-08';

function dailyRun(array $options = []): PendingCommand
{
    return artisan('billing:daily', ['--date' => DAILY_DAY, ...$options]);
}

function dailySchool(array $attributes = []): Tenant
{
    return TenantFactory::new()->create(['billing_email' => 'keuangan@sekolah.test', ...$attributes]);
}

/** An active, paid subscription whose period ends on $periodEnd. */
function dailyPaid(Tenant $tenant, string $periodEnd, array $state = []): Subscription
{
    $end = Carbon::parse($periodEnd);

    return SubscriptionFactory::new()->forTenant($tenant->id)->state([
        'status' => SubscriptionStatus::Active,
        'trial_ends_at' => null,
        'current_period_start' => $end->copy()->subMonth(),
        'current_period_end' => $end,
        ...$state,
    ])->create();
}

function dailyTrial(Tenant $tenant, string $trialEnds): Subscription
{
    return SubscriptionFactory::new()->forTenant($tenant->id)->state(['trial_ends_at' => Carbon::parse($trialEnds)])->create();
}

function dailyInvoice(Subscription $subscription, string $dueOn, string $issuedOn, array $state = []): Invoice
{
    return InvoiceFactory::new()->create([
        'tenant_id' => $subscription->tenant_id,
        'subscription_id' => $subscription->id,
        'plan_id' => $subscription->plan_id,
        'issued_at' => Carbon::parse($issuedOn),
        'due_at' => Carbon::parse($dueOn),
        ...$state,
    ]);
}

function dailyNotices(BillingNoticeKind $kind): int
{
    return BillingNotice::query()->where('kind', $kind)->count();
}

beforeEach(function () {
    Mail::fake();
});

describe('renewal invoices', function () {
    it('issues the renewal invoice 14 days before the period ends, once', function () {
        $tenant = dailySchool();
        $subscription = dailyPaid($tenant, '2026-10-22');

        dailyRun()->assertSuccessful();
        dailyRun()->assertSuccessful();

        $invoice = Invoice::query()->where('subscription_id', $subscription->id)->sole();

        expect($invoice->kind)->toBe(InvoiceKind::Renewal)
            ->and($invoice->status)->toBe(InvoiceStatus::Unpaid)
            ->and($invoice->due_at->toDateString())->toBe('2026-10-22')
            ->and($invoice->period_start->toDateString())->toBe('2026-10-22');

        Mail::assertQueued(InvoiceIssuedMail::class, 1);
    });

    it('waits until the period is 14 days away', function () {
        dailyPaid(dailySchool(), '2026-10-23');

        dailyRun()->assertSuccessful();

        expect(Invoice::query()->count())->toBe(0);
    });

    it('also issues an invoice to a subscription that has already lapsed', function () {
        $subscription = dailyPaid(dailySchool(), '2026-10-01');

        dailyRun()->assertSuccessful();

        expect(Invoice::query()->where('subscription_id', $subscription->id)->count())->toBe(1);
    });

    it('leaves out a cancelled subscription, a trial, a billing-exempt school and one with an invoice already open', function () {
        dailyPaid(dailySchool(), '2026-10-15', ['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()]);
        dailyTrial(dailySchool(), '2026-10-12');
        dailyPaid(dailySchool(['billing_exempt' => true]), '2026-10-15');
        $withOpen = dailyPaid(dailySchool(), '2026-10-15');
        $upgrade = dailyInvoice($withOpen, '2026-10-12', '2026-10-05', ['kind' => InvoiceKind::Upgrade]);

        dailyRun()->assertSuccessful();

        expect(Invoice::query()->count())->toBe(1)
            ->and($upgrade->refresh()->status)->toBe(InvoiceStatus::Unpaid);
    });

    it('plans without changing anything in a dry run', function () {
        dailyPaid(dailySchool(), '2026-10-22');

        dailyRun(['--dry-run' => true])->assertSuccessful()->expectsOutputToContain('invoice perpanjangan');

        expect(Invoice::query()->count())->toBe(0);
        Mail::assertNothingQueued();
    });
});

describe('reminders', function () {
    it('reminds a trial three days before it ends, once', function () {
        $subscription = dailyTrial(dailySchool(), '2026-10-11');
        dailyTrial(dailySchool(), '2026-10-19');

        dailyRun()->assertSuccessful();
        dailyRun()->assertSuccessful();

        expect(dailyNotices(BillingNoticeKind::TrialEnding))->toBe(1)
            ->and(BillingNotice::query()->where('subscription_id', $subscription->id)->value('anchor_date')->toDateString())->toBe('2026-10-08');

        Mail::assertQueued(TrialEndingMail::class, 1);
    });

    it('does not remind a trial that already has an invoice open: the invoice reminders cover it', function () {
        $subscription = dailyTrial(dailySchool(), '2026-10-10');
        dailyInvoice($subscription, '2026-10-20', '2026-10-01');

        dailyRun()->assertSuccessful();

        expect(dailyNotices(BillingNoticeKind::TrialEnding))->toBe(0);
    });

    it('reminds 7 days and 1 day before an invoice falls due, each once', function () {
        $subscription = dailyPaid(dailySchool(), '2026-10-15');
        dailyInvoice($subscription, '2026-10-15', '2026-10-01');

        dailyRun()->assertSuccessful();
        dailyRun()->assertSuccessful();
        artisan('billing:daily', ['--date' => '2026-10-14'])->assertSuccessful();
        artisan('billing:daily', ['--date' => '2026-10-14'])->assertSuccessful();

        $anchors = BillingNotice::query()->where('kind', BillingNoticeKind::DueReminder)->orderBy('anchor_date')
            ->get()->map(fn (BillingNotice $notice): string => $notice->anchor_date->toDateString())->all();

        expect($anchors)->toBe(['2026-10-08', '2026-10-14']);
        Mail::assertQueued(DueReminderMail::class, 2);
    });

    it('sends only the newest reminder when a run comes late, and none that went stale', function () {
        $subscription = dailyPaid(dailySchool(), '2026-10-09');
        dailyInvoice($subscription, '2026-10-09', '2026-09-20');

        // Two days were missed: day -7 passed long ago, only day -1 (today) is sent.
        dailyRun()->assertSuccessful();

        expect(dailyNotices(BillingNoticeKind::DueReminder))->toBe(1)
            ->and(BillingNotice::query()->sole()->anchor_date->toDateString())->toBe('2026-10-08');
    });

    it('sends no due reminder on the day an invoice is issued', function () {
        $subscription = dailyPaid(dailySchool(), '2026-10-15');
        dailyInvoice($subscription, '2026-10-15', DAILY_DAY);

        dailyRun()->assertSuccessful();

        expect(dailyNotices(BillingNoticeKind::DueReminder))->toBe(0);
    });

    it('reminds a late invoice while access still runs, with the end of the grace period', function () {
        $subscription = dailyPaid(dailySchool(), '2026-10-06');
        dailyInvoice($subscription, '2026-10-06', '2026-09-29');

        dailyRun()->assertSuccessful();   // two days late: the +1 reminder is the newest due

        $notice = BillingNotice::query()->where('kind', BillingNoticeKind::OverdueReminder)->sole();

        expect($notice->anchor_date->toDateString())->toBe('2026-10-07');
        Mail::assertQueued(OverdueReminderMail::class, fn (OverdueReminderMail $mail): bool => $mail->graceEndsOn === '13/10/2026');
    });

    it('stops reminding once access has lapsed: the school is closed instead', function () {
        $subscription = dailyPaid(dailySchool(), '2026-09-25');
        dailyInvoice($subscription, '2026-09-25', '2026-09-18');

        dailyRun(['--only' => ['reminders']])->assertSuccessful();

        expect(dailyNotices(BillingNoticeKind::OverdueReminder))->toBe(0);
    });

    it('leaves out a billing-exempt school and changes nothing in a dry run', function () {
        $exempt = dailyPaid(dailySchool(['billing_exempt' => true]), '2026-10-15');
        dailyInvoice($exempt, '2026-10-15', '2026-10-01');
        $other = dailyPaid(dailySchool(), '2026-10-15');
        dailyInvoice($other, '2026-10-15', '2026-10-01');

        dailyRun(['--only' => ['reminders'], '--dry-run' => true])->assertSuccessful();
        expect(BillingNotice::query()->count())->toBe(0);

        dailyRun(['--only' => ['reminders']])->assertSuccessful();
        expect(BillingNotice::query()->sole()->tenant_id)->toBe($other->tenant_id);
    });
});

describe('suspension', function () {
    it('suspends a trial only after its grace period', function () {
        $inGrace = dailyTrial($graceSchool = dailySchool(), '2026-10-01');       // access to 2026-10-08
        $lapsed = dailyTrial($lapsedSchool = dailySchool(), '2026-09-30');       // access to 2026-10-07

        dailyRun()->assertSuccessful();

        expect($graceSchool->refresh()->status)->toBe(TenantStatus::Active)
            ->and($lapsedSchool->refresh()->status)->toBe(TenantStatus::Suspended)
            ->and($lapsedSchool->suspended_reason)->toBe(SuspensionReason::Billing)
            ->and($inGrace->refresh()->status)->toBe(SubscriptionStatus::Trial);

        Mail::assertQueued(AccessStoppedMail::class, 1);
    });

    it('suspends an active subscription seven days after its period ended', function () {
        $school = dailySchool();
        dailyPaid($school, '2026-10-01');
        $late = dailySchool();
        dailyPaid($late, '2026-09-30');

        dailyRun(['--only' => ['suspensions']])->assertSuccessful();

        expect($school->refresh()->status)->toBe(TenantStatus::Active)
            ->and($late->refresh()->status)->toBe(TenantStatus::Suspended);
    });

    it('closes a cancelled subscription the day after the end it had, with no grace', function () {
        $keeps = dailySchool();
        dailyPaid($keeps, '2026-10-08', ['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()]);
        $closes = dailySchool();
        dailyPaid($closes, '2026-10-07', ['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()]);

        dailyRun(['--only' => ['suspensions']])->assertSuccessful();

        expect($keeps->refresh()->status)->toBe(TenantStatus::Active)
            ->and($closes->refresh()->status)->toBe(TenantStatus::Suspended);
    });

    it('leaves a billing-exempt school and a school the provider closed alone', function () {
        $exempt = dailySchool(['billing_exempt' => true]);
        dailyTrial($exempt, '2026-08-01');
        $manual = TenantFactory::new()->suspended()->create(['billing_email' => 'keuangan@sekolah.test']);
        dailyTrial($manual, '2026-08-01');

        dailyRun(['--only' => ['suspensions']])->assertSuccessful();

        expect($exempt->refresh()->status)->toBe(TenantStatus::Active)
            ->and($manual->refresh()->suspended_reason)->toBe(SuspensionReason::Manual);
        Mail::assertNotQueued(AccessStoppedMail::class);
    });

    it('does nothing twice when it is run again', function () {
        $school = dailySchool();
        dailyTrial($school, '2026-09-01');

        dailyRun()->assertSuccessful();
        dailyRun()->assertSuccessful();

        Mail::assertQueued(AccessStoppedMail::class, 1);
    });

    it('lists the schools it would close in a dry run and closes none', function () {
        $school = dailySchool(['slug' => 'sekolah-tutup']);
        dailyTrial($school, '2026-09-01');

        dailyRun(['--dry-run' => true])->assertSuccessful()->expectsOutputToContain('sekolah-tutup');

        expect($school->refresh()->status)->toBe(TenantStatus::Active);
    });

    it('holds back a mass suspension past the cap until it is forced', function () {
        $schools = collect(range(1, 6))->map(function (): Tenant {
            $school = dailySchool();
            dailyTrial($school, '2026-08-01');

            return $school;
        });

        dailyRun(['--only' => ['suspensions']])->assertFailed();

        expect($schools->filter(fn (Tenant $school): bool => $school->refresh()->status === TenantStatus::Suspended))->toHaveCount(0)
            ->and(ProviderSetting::read('billing.suspend_blocked')['count'])->toBe(6);

        dailyRun(['--only' => ['suspensions'], '--force' => true])->assertSuccessful();

        expect($schools->filter(fn (Tenant $school): bool => $school->refresh()->status === TenantStatus::Suspended))->toHaveCount(6)
            ->and(ProviderSetting::read('billing.suspend_blocked'))->toBeNull();
    });

    it('holds back past a fifth of the billed schools, but only once there are enough of them', function () {
        // 10 billed schools, 3 lapsed = 30%.
        $lapsed = collect(range(1, 3))->map(function (): Tenant {
            $school = dailySchool();
            dailyTrial($school, '2026-08-01');

            return $school;
        });
        collect(range(1, 7))->each(fn () => dailyTrial(dailySchool(), '2026-12-01'));

        dailyRun(['--only' => ['suspensions']])->assertFailed();
        expect($lapsed->first()->refresh()->status)->toBe(TenantStatus::Active);

        // With only 4 billed schools one overdue school would always be 25%.
        Tenant::query()->whereKeyNot($lapsed->first()->id)->get()->each(fn (Tenant $tenant) => $tenant->forceFill(['billing_exempt' => true])->save());
        $lapsed->slice(1)->each(fn (Tenant $tenant) => $tenant->forceFill(['billing_exempt' => true])->save());

        dailyRun(['--only' => ['suspensions']])->assertSuccessful();
        expect($lapsed->first()->refresh()->status)->toBe(TenantStatus::Suspended);
    });
});

describe('unfinished payments', function () {
    it('expires a gateway payment past its time and keeps the invoice unpaid', function () {
        $subscription = dailyPaid(dailySchool(), '2026-10-30');
        $invoice = dailyInvoice($subscription, '2026-10-30', '2026-10-08');
        $late = PaymentFactory::new()->forInvoice($invoice)->create(['status' => PaymentStatus::Pending, 'expires_at' => now()->subHour()]);
        $open = PaymentFactory::new()->forInvoice($invoice)->create(['status' => PaymentStatus::Pending, 'expires_at' => now()->addHour(), 'external_id' => 'x-2']);
        $manual = PaymentFactory::new()->forInvoice($invoice)->create(['status' => PaymentStatus::Pending, 'expires_at' => null, 'external_id' => 'x-3']);

        dailyRun(['--only' => ['payments']])->assertSuccessful();

        expect($late->refresh()->status)->toBe(PaymentStatus::Expired)
            ->and($open->refresh()->status)->toBe(PaymentStatus::Pending)
            ->and($manual->refresh()->status)->toBe(PaymentStatus::Pending)
            ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Unpaid);
    });
});

describe('the run itself', function () {
    it('goes on with the other schools when one fails, and reports it', function () {
        $broken = dailyPaid(dailySchool(['slug' => 'sekolah-rusak']), '2026-10-15');
        $fine = dailyPaid(dailySchool(), '2026-10-15');
        $broken->plan->delete();   // renew() cannot find the plan
        $fine->update(['plan_id' => PlanFactory::new()->create()->id]);

        dailyRun(['--only' => ['renewals']])->assertFailed();

        expect(Invoice::query()->where('subscription_id', $fine->id)->count())->toBe(1)
            ->and(Invoice::query()->where('subscription_id', $broken->id)->count())->toBe(0);
    });

    it('stamps the last run only after a full, real, successful run', function () {
        dailyRun(['--dry-run' => true])->assertSuccessful();
        dailyRun(['--only' => ['payments']])->assertSuccessful();
        dailyRun()->assertSuccessful();
        expect(ProviderSetting::read(BillingDaily::LAST_RUN_SETTING))->toBeNull();   // --date replays do not count

        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
        artisan('billing:daily')->assertSuccessful();

        expect(ProviderSetting::read(BillingDaily::LAST_RUN_SETTING))->not->toBeNull();
    });

    it('does not stamp the last run when a step failed', function () {
        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
        $broken = dailyPaid(dailySchool(), '2026-10-15');
        $broken->plan->delete();

        artisan('billing:daily')->assertFailed();

        expect(ProviderSetting::read(BillingDaily::LAST_RUN_SETTING))->toBeNull();
    });

    it('takes the billing day in Jakarta, not in UTC', function () {
        $this->travelTo(Carbon::parse('2026-10-07 20:00:00', 'UTC'));

        artisan('billing:daily', ['--dry-run' => true])->assertSuccessful()->expectsOutputToContain('Billing day 2026-10-08');
    });

    it('lifts the pinned day when the run is over', function () {
        $this->travelTo(Carbon::parse('2026-03-01 10:00:00', 'Asia/Jakarta'));

        dailyRun()->assertSuccessful();

        expect(BillingClock::today()->toDateString())->toBe('2026-03-01');
    });

    it('refuses an unknown step or a malformed date', function () {
        artisan('billing:daily', ['--only' => ['everything']])->assertExitCode(2);
        artisan('billing:daily', ['--date' => '08-10-2026'])->assertExitCode(2);
        artisan('billing:daily', ['--date' => '2026-02-31'])->assertExitCode(2);
    });
});

describe('the console banner', function () {
    it('warns until the job has finished a full run, and says when it holds a suspension back', function () {
        actingAs(ProviderUserFactory::new()->create(), 'provider');

        get('http://console.localhost/billing')->assertInertia(fn ($page) => $page
            ->where('daily.lastRunAt', null)
            ->where('daily.stale', true)
            ->where('daily.blocked', null));

        ProviderSetting::write(BillingDaily::LAST_RUN_SETTING, now()->toIso8601String());
        ProviderSetting::write('billing.suspend_blocked', ['at' => now()->toIso8601String(), 'count' => 7]);

        get('http://console.localhost/billing')->assertInertia(fn ($page) => $page
            ->where('daily.stale', false)
            ->where('daily.blocked.count', 7));

        ProviderSetting::write(BillingDaily::LAST_RUN_SETTING, now()->subDays(3)->toIso8601String());

        get('http://console.localhost/billing')->assertInertia(fn ($page) => $page->where('daily.stale', true));
    });
});
