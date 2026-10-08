<?php

namespace Modules\Platform\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Domain\Exceptions\BillingException;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\ProviderUser;
use Modules\Platform\App\Domain\Support\BillingClock;
use Modules\Platform\App\Http\Support\ConsoleResources;
use Modules\Platform\App\Infrastructure\Billing\InvoiceIssuer;
use Modules\Platform\App\Infrastructure\Billing\PaymentGateway;
use Modules\Platform\App\Infrastructure\Billing\PaymentOutcome;
use Modules\Platform\App\Infrastructure\Billing\SubscriptionManager;

/**
 * Provider console invoices: list, confirm a payment and void. Nothing is
 * ever deleted.
 */
final class InvoiceController
{
    /** How the provider received the money (payments.method). */
    private const METHODS = ['bank_transfer', 'cash', 'other'];

    public function __construct(
        private readonly SubscriptionManager $subscriptions,
        private readonly InvoiceIssuer $issuer,
        private readonly PaymentGateway $gateway,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:unpaid,paid,void,overdue'],
        ]);

        $invoices = Invoice::query()
            ->with('tenant')
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $query->where(fn ($inner) => $inner
                    ->where('number', 'like', $like)
                    ->orWhereHas('tenant', fn ($tenant) => $tenant->where('name', 'like', $like)));
            })
            ->when($filters['status'] ?? null, function ($query, string $status): void {
                if ($status === 'overdue') {
                    $query->where('status', InvoiceStatus::Unpaid)->where('due_at', '<', BillingClock::today());

                    return;
                }

                $query->where('status', $status);
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Invoice $invoice): array => ConsoleResources::invoice($invoice));

        return Inertia::render('Platform/Billing/Invoices', [
            'invoices' => $invoices,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
        ]);
    }

    /**
     * The provider confirms the school's transfer by hand. The gateway
     * supplies the pending payment; settle() does the rest and is a no-op
     * when the invoice is already paid.
     */
    public function confirm(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'method' => ['required', Rule::in(self::METHODS)],
            'reference' => ['nullable', 'required_if:method,bank_transfer', 'string', 'max:100'],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.BillingClock::today()->toDateString()],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var ProviderUser $provider */
        $provider = $request->user('provider');

        try {
            if ($invoice->status !== InvoiceStatus::Unpaid) {
                throw new BillingException("Invoice [{$invoice->number}] is {$invoice->status->value} and cannot be paid.");
            }

            $this->subscriptions->settle(
                $this->gateway->initiate($invoice),
                PaymentOutcome::paid(
                    amount: $invoice->amount,
                    method: $validated['method'],
                    reference: $validated['reference'] ?? null,
                    paidOn: Carbon::createFromFormat('Y-m-d', $validated['paid_on'])->startOfDay(),
                    confirmedBy: (int) $provider->id,
                    note: $validated['note'] ?? null,
                ),
            );
        } catch (BillingException $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()]);
        }

        return back()->with('status', "Tagihan {$invoice->number} lunas.");
    }

    public function void(Invoice $invoice): RedirectResponse
    {
        try {
            $this->issuer->void($invoice);
        } catch (BillingException $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()]);
        }

        return back()->with('status', "Tagihan {$invoice->number} dibatalkan.");
    }
}
