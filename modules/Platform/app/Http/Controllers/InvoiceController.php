<?php

namespace Modules\Platform\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Domain\Exceptions\BillingException;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Http\Support\ConsoleResources;
use Modules\Platform\App\Infrastructure\Billing\InvoiceIssuer;
use Modules\Platform\App\Infrastructure\Billing\SubscriptionManager;

/**
 * Provider console invoices: list, mark paid (through the payment
 * gateway) and void. Nothing is ever deleted.
 */
final class InvoiceController
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
        private readonly InvoiceIssuer $issuer,
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
                    $query->where('status', InvoiceStatus::Unpaid)->where('due_at', '<', Carbon::today());

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

    public function pay(Invoice $invoice): RedirectResponse
    {
        try {
            $paid = $this->subscriptions->payInvoice($invoice);
        } catch (BillingException $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()]);
        }

        if (! $paid) {
            return back()->withErrors(['billing' => "Pembayaran {$invoice->number} gagal diproses."]);
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
