<?php

namespace Modules\Platform\App\Infrastructure\Billing;

use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\Payment;
use Modules\Platform\App\Domain\Models\PaymentStatus;

/**
 * Bank transfer to the provider's own account. Nothing is charged here:
 * initiate() records a pending Payment (reused while one is open) with the
 * account to transfer to, and the provider confirms it by hand.
 */
final class ManualTransferGateway implements PaymentGateway
{
    public const KEY = 'manual_transfer';

    public function initiate(Invoice $invoice): Payment
    {
        $open = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('tenant_id', $invoice->tenant_id)
            ->where('gateway', self::KEY)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        if ($open !== null) {
            return $open;
        }

        return Payment::query()->create([
            'invoice_id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'amount' => $invoice->amount,
            'gateway' => self::KEY,
            'status' => PaymentStatus::Pending,
            'meta' => ['instructions' => $this->instructions()],
        ]);
    }

    /**
     * @return array{bank: ?string, account: ?string, holder: ?string}
     */
    private function instructions(): array
    {
        return [
            'bank' => config('billing.issuer.bank.name'),
            'account' => config('billing.issuer.bank.account'),
            'holder' => config('billing.issuer.bank.holder'),
        ];
    }
}
