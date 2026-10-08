<?php

namespace Modules\Platform\App\Infrastructure\Billing;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Tenant;

/**
 * The invoice as a PDF, rendered on demand and never stored. One document
 * serves as invoice and as receipt: the status stamp (Belum dibayar /
 * Lunas / Dibatalkan) tells which. The provider's identity and bank
 * account are read live from config('billing.issuer'), so a changed
 * account shows on every document rendered afterwards.
 */
final class InvoiceDocument
{
    public function render(Invoice $invoice): string
    {
        $tenant = Tenant::withTrashed()->find($invoice->tenant_id);

        return Pdf::loadView('Platform::invoice-pdf', [
            'invoice' => $invoice,
            'schoolName' => $tenant->name ?? '-',
            'contactName' => $tenant?->billing_name,
            'contactEmail' => $tenant?->billing_email,
            'issuer' => (array) config('billing.issuer', []),
            'stamp' => $this->stamp($invoice),
        ])->setPaper('a4')->output();
    }

    public function filename(Invoice $invoice): string
    {
        return $invoice->number.'.pdf';
    }

    public function stamp(Invoice $invoice): string
    {
        return match ($invoice->status) {
            InvoiceStatus::Paid => 'Lunas',
            InvoiceStatus::Void => 'Dibatalkan',
            default => 'Belum dibayar',
        };
    }
}
