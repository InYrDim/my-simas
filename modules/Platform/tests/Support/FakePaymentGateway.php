<?php

namespace Modules\Platform\Tests\Support;

use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\Payment;
use Modules\Platform\App\Domain\Models\PaymentStatus;
use Modules\Platform\App\Infrastructure\Billing\PaymentGateway;
use Modules\Platform\App\Infrastructure\Billing\PaymentOutcome;

/**
 * Gateway test double with a configurable result. initiate() opens a
 * pending Payment like a real gateway; outcome() is what its webhook
 * would report for it, to be handed to SubscriptionManager::settle().
 */
final class FakePaymentGateway implements PaymentGateway
{
    private int $sequence = 0;

    public function __construct(private readonly PaymentStatus $result = PaymentStatus::Paid) {}

    public static function paying(): self
    {
        return new self(PaymentStatus::Paid);
    }

    public static function failing(): self
    {
        return new self(PaymentStatus::Failed);
    }

    public static function expiring(): self
    {
        return new self(PaymentStatus::Expired);
    }

    public function initiate(Invoice $invoice): Payment
    {
        return Payment::query()->create([
            'invoice_id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'amount' => $invoice->amount,
            'gateway' => 'fake',
            'status' => PaymentStatus::Pending,
            'meta' => ['instructions' => ['va' => '8000'.str_pad((string) $invoice->id, 8, '0', STR_PAD_LEFT)]],
        ]);
    }

    /**
     * @param  int|null  $amount  Defaults to the invoice amount; pass another to simulate a wrong payment.
     */
    public function outcome(Invoice $invoice, ?int $amount = null, ?string $externalId = null): PaymentOutcome
    {
        return match ($this->result) {
            PaymentStatus::Failed => PaymentOutcome::failed('declined'),
            PaymentStatus::Expired => PaymentOutcome::expired('expired'),
            default => PaymentOutcome::paid(
                amount: $amount ?? $invoice->amount,
                method: 'fake',
                externalId: $externalId ?? 'fake-'.$invoice->id.'-'.++$this->sequence,
            ),
        };
    }
}
