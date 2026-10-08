<?php

namespace Modules\Platform\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Domain\Exceptions\BillingException;
use Modules\Platform\App\Domain\Models\BillingNotice;
use Modules\Platform\App\Domain\Models\BillingNoticeStatus;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Payment;
use Modules\Platform\App\Domain\Models\PaymentStatus;
use Modules\Platform\App\Domain\Models\ProviderUser;
use Modules\Platform\App\Domain\Support\BillingClock;
use Modules\Platform\App\Http\Support\ConsoleResources;
use Modules\Platform\App\Infrastructure\Billing\BillingNotifier;
use Modules\Platform\App\Infrastructure\Billing\InvoiceDocument;
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
        private readonly BillingNotifier $notifier,
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

        // What a school said about a transfer it made, from its waiting payment.
        $reported = Payment::query()
            ->whereIn('invoice_id', $invoices->getCollection()->pluck('id'))
            ->where('status', PaymentStatus::Pending)
            ->orderBy('id')
            ->get()
            ->keyBy('invoice_id');

        $notices = BillingNotice::query()
            ->whereIn('invoice_id', $invoices->getCollection()->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('invoice_id');

        $invoices->through(fn (array $invoice): array => [
            ...$invoice,
            'reportedTransfer' => $reported->get($invoice['id'])?->meta['reported'] ?? null,
            'notices' => ($notices->get($invoice['id']) ?? collect())
                ->map(fn (BillingNotice $notice): array => [
                    'id' => $notice->id,
                    'kind' => $notice->kind->value,
                    'recipient' => $notice->recipient,
                    'status' => $notice->status->value,
                    'error' => $notice->error,
                    'at' => ($notice->sent_at ?? $notice->created_at)?->toDateTimeString(),
                ])->values()->all(),
        ]);

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

    /**
     * The invoice (or, once paid, the receipt) as a PDF download.
     */
    public function pdf(Invoice $invoice, InvoiceDocument $document): HttpResponse
    {
        return response($document->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$document->filename($invoice).'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Queue the invoice email again (the receipt when the invoice is
     * paid) and record it in the notice history.
     */
    public function resend(Invoice $invoice): RedirectResponse
    {
        try {
            $notice = $this->notifier->resend($invoice);
        } catch (BillingException $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()]);
        }

        if ($notice->status === BillingNoticeStatus::Failed) {
            return back()->withErrors(['billing' => 'Email tidak dikirim: '.$notice->error]);
        }

        return back()->with('status', "Email {$invoice->number} dikirim ulang ke {$notice->recipient}.");
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
