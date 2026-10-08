<?php

namespace Modules\Platform\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\Payment;
use Modules\Platform\App\Domain\Models\PaymentStatus;

/**
 * Pending manual-transfer payment. invoice_id, tenant_id and amount come
 * from the caller (or forInvoice()).
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => 0,
            'tenant_id' => TenantFactory::new(),
            'amount' => 150_000,
            'gateway' => 'manual_transfer',
            'method' => null,
            'status' => PaymentStatus::Pending,
        ];
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn (): array => [
            'invoice_id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'amount' => $invoice->amount,
        ]);
    }
}
