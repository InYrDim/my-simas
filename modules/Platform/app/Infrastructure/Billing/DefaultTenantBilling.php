<?php

namespace Modules\Platform\App\Infrastructure\Billing;

use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use Modules\Platform\App\Contracts\DTOs\BillingOverview;
use Modules\Platform\App\Contracts\DTOs\InvoiceFile;
use Modules\Platform\App\Contracts\DTOs\InvoiceSummary;
use Modules\Platform\App\Contracts\DTOs\PaymentInstructions;
use Modules\Platform\App\Contracts\DTOs\PlanChangeOutcome;
use Modules\Platform\App\Contracts\DTOs\PlanOffer;
use Modules\Platform\App\Contracts\DTOs\UsageLine;
use Modules\Platform\App\Contracts\Exceptions\BillingActionRefusedException;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\TenantBilling;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Platform\App\Contracts\TenantUsage;
use Modules\Platform\App\Domain\Exceptions\BillingException;
use Modules\Platform\App\Domain\Models\BillingCycle;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Payment;
use Modules\Platform\App\Domain\Models\PaymentStatus;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Support\BillingClock;
use Modules\Platform\App\Infrastructure\Usage\DefaultUsageMeters;

/**
 * The school's own door to its subscription. Everything is scoped to the
 * ambient tenant: `invoices`, `subscriptions` and `payments` are central
 * tables with no tenant scope, so every query here names the tenant.
 *
 * It only calls the provider-side services (SubscriptionManager, the
 * payment gateway, the invoice document); the rules live there. What this
 * class adds is who may do what (public plans, the right subscription
 * status) and wording a school can read.
 */
final class DefaultTenantBilling implements TenantBilling
{
    private const INVOICE_LIMIT = 24;

    public function __construct(
        private readonly TenantContext $context,
        private readonly SubscriptionManager $subscriptions,
        private readonly PaymentGateway $gateway,
        private readonly InvoiceDocument $documents,
        private readonly TenantUsage $usage,
        private readonly DefaultUsageMeters $meters,
        private readonly ModuleRegistry $registry,
        private readonly TenantModules $modules,
    ) {}

    public function overview(): BillingOverview
    {
        $tenantId = $this->tenantId();
        $subscription = $this->subscription($tenantId);
        $plan = $subscription?->plan;
        $exempt = $this->exempt($tenantId);

        $state = match (true) {
            $subscription === null => BillingOverview::STATE_NONE,
            $exempt => BillingOverview::STATE_EXEMPT,
            default => $subscription->displayState(),
        };

        $scheduled = $subscription?->scheduled_plan_id === null ? null : Plan::query()->find($subscription->scheduled_plan_id);

        return new BillingOverview(
            state: $state,
            planKey: $plan?->key,
            planName: $plan?->name,
            cycle: $subscription?->billing_cycle->value,
            price: $plan === null ? null : $plan->priceFor($subscription->billing_cycle),
            trialEndsOn: $subscription?->trial_ends_at?->toDateString(),
            periodStart: $subscription?->current_period_start?->toDateString(),
            periodEnd: $subscription?->current_period_end?->toDateString(),
            accessEndsOn: $subscription?->accessEndsAt()?->toDateString(),
            scheduledPlanKey: $scheduled?->key,
            scheduledPlanName: $scheduled?->name,
            modules: $this->activeModules($tenantId),
            usage: $this->usage->forTenant($tenantId),
            canSubscribe: $subscription !== null && ! $exempt && $this->mayChoosePlanAnew($subscription),
            canChangePlan: $subscription !== null && ! $exempt && $this->mayChangePlan($subscription),
        );
    }

    public function invoices(): array
    {
        $tenantId = $this->tenantId();

        $invoices = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->limit(self::INVOICE_LIMIT)
            ->get();

        $waiting = Payment::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->where('status', PaymentStatus::Pending)
            ->orderBy('id')
            ->get()
            ->keyBy('invoice_id');

        return $invoices
            ->map(fn (Invoice $invoice): InvoiceSummary => $this->summary($invoice, $waiting->get($invoice->id)))
            ->values()
            ->all();
    }

    public function offers(): array
    {
        $tenantId = $this->tenantId();
        $subscription = $this->subscription($tenantId);
        $current = $subscription?->plan;
        $usage = $this->usage->forTenant($tenantId);

        return Plan::query()
            ->selectable()
            ->public()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Plan $plan): PlanOffer => $this->offer($plan, $current, $subscription, $usage))
            ->values()
            ->all();
    }

    public function subscribe(string $planKey, string $cycle): InvoiceSummary
    {
        $tenantId = $this->tenantId();
        $subscription = $this->requireSubscription($tenantId);
        $this->refuseWhenExempt($tenantId);

        if (! $this->mayChoosePlanAnew($subscription)) {
            throw new BillingActionRefusedException('Langganan sudah berjalan. Invoice perpanjangan diterbitkan otomatis menjelang periode berakhir.');
        }

        $this->requirePublicPlan($planKey);

        try {
            $invoice = $this->subscriptions->activate($subscription, $planKey, $this->cycle($cycle));
        } catch (BillingException $exception) {
            throw $this->refusal($exception);
        }

        return $this->summary($invoice, null);
    }

    public function startPayment(string $invoiceNumber): PaymentInstructions
    {
        $invoice = $this->unpaidInvoice($invoiceNumber);

        return $this->instructions($invoice, $this->gateway->initiate($invoice));
    }

    public function reportTransfer(string $invoiceNumber, string $transferredOn, string $bank, string $senderName, ?string $reference = null): void
    {
        $invoice = $this->unpaidInvoice($invoiceNumber);
        $date = $this->date($transferredOn);

        if ($date->gt(BillingClock::today())) {
            throw new BillingActionRefusedException('Tanggal transfer tidak boleh di masa depan.');
        }

        $payment = $this->gateway->initiate($invoice);

        $payment->forceFill([
            'meta' => array_merge($payment->meta ?? [], ['reported' => [
                'transferredOn' => $date->toDateString(),
                'bank' => trim($bank),
                'senderName' => trim($senderName),
                'reference' => $reference === null || trim($reference) === '' ? null : trim($reference),
                'reportedAt' => now()->toIso8601String(),
            ]]),
        ])->save();
    }

    public function changePlan(string $planKey): PlanChangeOutcome
    {
        $tenantId = $this->tenantId();
        $subscription = $this->requireSubscription($tenantId);
        $this->refuseWhenExempt($tenantId);

        if (! $this->mayChangePlan($subscription)) {
            throw new BillingActionRefusedException('Paket hanya bisa diganti saat uji coba atau selama periode berbayar berjalan. Perpanjang langganan lebih dulu.');
        }

        $this->requirePublicPlan($planKey);

        try {
            $result = $this->subscriptions->requestPlanChange($subscription, $planKey);
        } catch (BillingException $exception) {
            throw $this->refusal($exception);
        }

        return match (true) {
            $result->isUpgrade() => new PlanChangeOutcome(
                PlanChangeOutcome::UPGRADE,
                invoice: $this->summary($result->upgradeInvoice, null),
            ),
            $result->isScheduled() => new PlanChangeOutcome(
                PlanChangeOutcome::SCHEDULED,
                scheduledPlanName: $result->scheduledPlan->name,
                takesEffectOn: $subscription->current_period_end?->toDateString(),
            ),
            default => new PlanChangeOutcome(PlanChangeOutcome::IMMEDIATE),
        };
    }

    public function cancelScheduledChange(): void
    {
        $tenantId = $this->tenantId();
        $subscription = $this->requireSubscription($tenantId);
        $this->refuseWhenExempt($tenantId);

        $this->subscriptions->cancelScheduledChange($subscription);
    }

    public function changeCycle(string $cycle): void
    {
        $tenantId = $this->tenantId();
        $subscription = $this->requireSubscription($tenantId);
        $this->refuseWhenExempt($tenantId);

        if ($subscription->status === SubscriptionStatus::Cancelled) {
            throw new BillingActionRefusedException('Langganan sudah dihentikan. Pilih paket dan siklus saat berlangganan lagi.');
        }

        $this->subscriptions->changeCycle($subscription, $this->cycle($cycle));
    }

    public function invoicePdf(string $invoiceNumber): InvoiceFile
    {
        $invoice = $this->ownInvoice($invoiceNumber);

        return new InvoiceFile($this->documents->filename($invoice), $this->documents->render($invoice));
    }

    private function tenantId(): string
    {
        return $this->context->currentOrFail()->id;
    }

    private function subscription(string $tenantId): ?Subscription
    {
        return Subscription::query()->with('plan')->where('tenant_id', $tenantId)->first();
    }

    private function requireSubscription(string $tenantId): Subscription
    {
        return $this->subscription($tenantId)
            ?? throw new BillingActionRefusedException('Sekolah ini belum punya langganan. Hubungi penyedia layanan.');
    }

    private function exempt(string $tenantId): bool
    {
        return (bool) Tenant::query()->whereKey($tenantId)->value('billing_exempt');
    }

    private function refuseWhenExempt(string $tenantId): void
    {
        if ($this->exempt($tenantId)) {
            throw new BillingActionRefusedException('Sekolah ini tidak dikenai tagihan.');
        }
    }

    /**
     * Choosing a plan and cycle from scratch: on a trial (running or
     * expired) or after the subscription was cancelled.
     */
    private function mayChoosePlanAnew(Subscription $subscription): bool
    {
        return in_array($subscription->status, [SubscriptionStatus::Trial, SubscriptionStatus::Cancelled], true);
    }

    /**
     * Changing plan: on a trial, or while a paid period still runs.
     */
    private function mayChangePlan(Subscription $subscription): bool
    {
        if ($subscription->status === SubscriptionStatus::Trial) {
            return true;
        }

        return $subscription->status === SubscriptionStatus::Active
            && $subscription->current_period_end !== null
            && $subscription->current_period_end->gte(BillingClock::today());
    }

    private function requirePublicPlan(string $planKey): void
    {
        if (! Plan::query()->selectable()->public()->where('key', $planKey)->exists()) {
            throw new BillingActionRefusedException('Paket tidak tersedia.');
        }
    }

    private function cycle(string $cycle): BillingCycle
    {
        return BillingCycle::tryFrom($cycle)
            ?? throw new BillingActionRefusedException('Siklus pembayaran harus bulanan atau tahunan.');
    }

    private function date(string $value): CarbonInterface
    {
        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (InvalidFormatException) {
            $date = null;
        }

        if ($date === null || $date->format('Y-m-d') !== $value) {
            throw new BillingActionRefusedException('Tanggal tidak valid.');
        }

        return $date;
    }

    private function ownInvoice(string $number): Invoice
    {
        return Invoice::query()
            ->where('tenant_id', $this->tenantId())
            ->where('number', $number)
            ->first()
            ?? throw BillingActionRefusedException::notFound();
    }

    private function unpaidInvoice(string $number): Invoice
    {
        $invoice = $this->ownInvoice($number);

        if ($invoice->status !== InvoiceStatus::Unpaid) {
            throw new BillingActionRefusedException('Invoice ini sudah tidak perlu dibayar.');
        }

        return $invoice;
    }

    /**
     * A refusal the school can read, for what SubscriptionManager reports
     * in English. Anything not listed is not for the school to see.
     */
    private function refusal(BillingException $exception): BillingActionRefusedException
    {
        $message = $exception->getMessage();

        return new BillingActionRefusedException(match (true) {
            str_contains($message, 'already on this plan') => 'Sekolah sudah memakai paket ini.',
            str_contains($message, 'no days left') => 'Periode berbayar hampir habis. Ganti paket setelah perpanjangan.',
            str_contains($message, 'Only a trial or a running paid period') => 'Paket hanya bisa diganti saat uji coba atau selama periode berbayar berjalan.',
            str_contains($message, 'does not exist or is not available') => 'Paket tidak tersedia.',
            default => 'Permintaan tidak bisa diproses. Hubungi penyedia layanan.',
        });
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function activeModules(string $tenantId): array
    {
        $modules = [];

        foreach ($this->registry->all() as $key => $meta) {
            if ($this->modules->isEnabled($key, $tenantId)) {
                $modules[] = ['key' => $key, 'label' => $meta['label'] ?? $key];
            }
        }

        return $modules;
    }

    private function summary(Invoice $invoice, ?Payment $waiting): InvoiceSummary
    {
        $unpaid = $invoice->status === InvoiceStatus::Unpaid;

        return new InvoiceSummary(
            number: $invoice->number,
            kind: $invoice->kind->value,
            status: $invoice->displayState(),
            planName: $invoice->plan_name,
            cycle: $invoice->billing_cycle->value,
            amount: $invoice->amount,
            issuedOn: $invoice->issued_at->toDateString(),
            dueOn: $invoice->due_at->toDateString(),
            periodStart: $invoice->period_start->toDateString(),
            periodEnd: $invoice->period_end->toDateString(),
            paidOn: $invoice->paid_at?->toDateString(),
            payable: $unpaid,
            instructions: $unpaid && $waiting !== null ? $this->instructions($invoice, $waiting) : null,
        );
    }

    private function instructions(Invoice $invoice, Payment $payment): PaymentInstructions
    {
        $bank = (array) ($payment->meta['instructions'] ?? []);
        $reported = $payment->meta['reported'] ?? null;

        return new PaymentInstructions(
            invoiceNumber: $invoice->number,
            amount: $invoice->amount,
            bankName: $bank['bank'] ?? null,
            bankAccount: $bank['account'] ?? null,
            accountHolder: $bank['holder'] ?? null,
            note: "Transfer sesuai nominal dan cantumkan nomor invoice {$invoice->number} pada berita transfer.",
            reportedTransfer: is_array($reported) ? [
                'transferredOn' => (string) ($reported['transferredOn'] ?? ''),
                'bank' => (string) ($reported['bank'] ?? ''),
                'senderName' => (string) ($reported['senderName'] ?? ''),
                'reference' => $reported['reference'] ?? null,
            ] : null,
        );
    }

    /**
     * @param  list<UsageLine>  $usage
     */
    private function offer(Plan $plan, ?Plan $current, ?Subscription $subscription, array $usage): PlanOffer
    {
        $direction = null;
        $modulesLost = [];
        $overLimits = [];

        if ($current !== null && $subscription !== null) {
            $cycle = $subscription->billing_cycle;

            $direction = match (true) {
                $plan->id === $current->id => PlanOffer::CURRENT,
                $plan->priceFor($cycle) > $current->priceFor($cycle) => PlanOffer::UPGRADE,
                default => PlanOffer::DOWNGRADE,
            };

            if ($direction !== PlanOffer::CURRENT) {
                $modulesLost = $this->modulesLost($current, $plan);
                $overLimits = $this->overLimits($plan, $usage);
            }
        }

        return new PlanOffer(
            key: $plan->key,
            name: $plan->name,
            priceMonthly: $plan->price_monthly,
            priceYearly: $plan->price_yearly,
            limits: [
                'students' => $plan->limit('students'),
                'staffAccounts' => $plan->limit('staff_accounts'),
                'storageMb' => $plan->limit('storage_mb'),
            ],
            modules: array_map(
                fn (string $key): array => ['key' => $key, 'label' => $this->registry->all()[$key]['label'] ?? $key],
                array_values($plan->modules),
            ),
            direction: $direction,
            modulesLost: $modulesLost,
            overLimits: $overLimits,
        );
    }

    /**
     * @return list<string>
     */
    private function modulesLost(Plan $from, Plan $to): array
    {
        return array_values(array_map(
            fn (string $key): string => $this->registry->all()[$key]['label'] ?? $key,
            array_diff($from->modules, $to->modules),
        ));
    }

    /**
     * The measured figures that would be above this plan's limits.
     *
     * @param  list<UsageLine>  $usage
     * @return list<array{label: string, unit: string, used: int, limit: int}>
     */
    private function overLimits(Plan $plan, array $usage): array
    {
        $limitKeys = collect($this->meters->all())->mapWithKeys(fn (array $meter): array => [$meter['key'] => $meter['limitKey']]);
        $over = [];

        foreach ($usage as $line) {
            $limit = $plan->limit($limitKeys->get($line->key, $line->key));

            if ($limit !== null && $line->used > $limit) {
                $over[] = ['label' => $line->label, 'unit' => $line->unit, 'used' => $line->used, 'limit' => $limit];
            }
        }

        return $over;
    }
}
