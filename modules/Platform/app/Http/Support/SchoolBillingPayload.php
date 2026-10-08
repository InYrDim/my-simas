<?php

namespace Modules\Platform\App\Http\Support;

use Illuminate\Support\Facades\Gate;
use Modules\Platform\App\Contracts\DTOs\InvoiceSummary;
use Modules\Platform\App\Contracts\DTOs\PaymentInstructions;
use Modules\Platform\App\Contracts\DTOs\PlanOffer;
use Modules\Platform\App\Contracts\DTOs\UsageLine;
use Modules\Platform\App\Contracts\TenantBilling;

/**
 * The shared Inertia prop `billing` the school's account panels read
 * (camelCase, `Y-m-d` dates, whole rupiah), with the URL of every action
 * built here. The panels live in Shared, which may not import Platform,
 * so they get their links from this prop instead of from Wayfinder.
 *
 * Only the facts the user may see are put in: without `platform.billing.pay`
 * the offers, the action links and the payment instructions are left out.
 */
final class SchoolBillingPayload
{
    public function __construct(
        private readonly TenantBilling $billing,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function build(): ?array
    {
        if (! Gate::allows('platform.billing.view')) {
            return null;
        }

        $canPay = Gate::allows('platform.billing.pay');
        $overview = $this->billing->overview();

        return [
            'canPay' => $canPay,
            'overview' => [
                'state' => $overview->state,
                'planKey' => $overview->planKey,
                'planName' => $overview->planName,
                'cycle' => $overview->cycle,
                'price' => $overview->price,
                'trialEndsOn' => $overview->trialEndsOn,
                'periodStart' => $overview->periodStart,
                'periodEnd' => $overview->periodEnd,
                'accessEndsOn' => $overview->accessEndsOn,
                'scheduledPlanKey' => $overview->scheduledPlanKey,
                'scheduledPlanName' => $overview->scheduledPlanName,
                'modules' => $overview->modules,
                'usage' => array_map(fn (UsageLine $line): array => [
                    'key' => $line->key,
                    'label' => $line->label,
                    'unit' => $line->unit,
                    'used' => $line->used,
                    'limit' => $line->limit,
                    'state' => $line->state,
                ], $overview->usage),
                'canSubscribe' => $canPay && $overview->canSubscribe,
                'canChangePlan' => $canPay && $overview->canChangePlan,
            ],
            'invoices' => array_map(
                fn (InvoiceSummary $invoice): array => $this->invoice($invoice, $canPay),
                $this->billing->invoices(),
            ),
            'offers' => $canPay && ($overview->canSubscribe || $overview->canChangePlan)
                ? array_map(fn (PlanOffer $offer): array => $this->offer($offer), $this->billing->offers())
                : [],
            'urls' => $canPay ? [
                'subscribe' => route('school.billing.subscribe'),
                'changePlan' => route('school.billing.plan'),
                'cancelScheduledChange' => route('school.billing.plan.cancel'),
                'changeCycle' => route('school.billing.cycle'),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoice(InvoiceSummary $invoice, bool $canPay): array
    {
        return [
            'number' => $invoice->number,
            'kind' => $invoice->kind,
            'status' => $invoice->status,
            'planName' => $invoice->planName,
            'cycle' => $invoice->cycle,
            'amount' => $invoice->amount,
            'issuedOn' => $invoice->issuedOn,
            'dueOn' => $invoice->dueOn,
            'periodStart' => $invoice->periodStart,
            'periodEnd' => $invoice->periodEnd,
            'paidOn' => $invoice->paidOn,
            'payable' => $canPay && $invoice->payable,
            'instructions' => $canPay && $invoice->instructions !== null ? $this->instructions($invoice->instructions) : null,
            'urls' => $canPay ? [
                'pay' => route('school.billing.pay', ['invoice' => $invoice->number]),
                'reportTransfer' => route('school.billing.report', ['invoice' => $invoice->number]),
                'pdf' => route('school.billing.pdf', ['invoice' => $invoice->number]),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function instructions(PaymentInstructions $instructions): array
    {
        return [
            'amount' => $instructions->amount,
            'bankName' => $instructions->bankName,
            'bankAccount' => $instructions->bankAccount,
            'accountHolder' => $instructions->accountHolder,
            'note' => $instructions->note,
            'reportedTransfer' => $instructions->reportedTransfer,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function offer(PlanOffer $offer): array
    {
        return [
            'key' => $offer->key,
            'name' => $offer->name,
            'priceMonthly' => $offer->priceMonthly,
            'priceYearly' => $offer->priceYearly,
            'limits' => $offer->limits,
            'modules' => $offer->modules,
            'direction' => $offer->direction,
            'modulesLost' => $offer->modulesLost,
            'overLimits' => $offer->overLimits,
        ];
    }
}
