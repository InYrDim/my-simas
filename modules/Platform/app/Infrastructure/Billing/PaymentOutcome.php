<?php

namespace Modules\Platform\App\Infrastructure\Billing;

use Carbon\CarbonInterface;
use Modules\Platform\App\Domain\Models\PaymentStatus;

/**
 * What happened to a Payment, as reported by whoever knows: the provider
 * confirming a transfer by hand, or a gateway webhook. Handed to
 * SubscriptionManager::settle().
 */
final readonly class PaymentOutcome
{
    /**
     * @param  array<string, mixed>  $meta
     */
    private function __construct(
        public PaymentStatus $status,
        public ?int $amount = null,
        public ?string $method = null,
        public ?string $reference = null,
        public ?string $externalId = null,
        public ?CarbonInterface $paidOn = null,
        public ?int $confirmedBy = null,
        public ?string $note = null,
        public array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function paid(
        int $amount,
        ?string $method = null,
        ?string $reference = null,
        ?string $externalId = null,
        ?CarbonInterface $paidOn = null,
        ?int $confirmedBy = null,
        ?string $note = null,
        array $meta = [],
    ): self {
        return new self(PaymentStatus::Paid, $amount, $method, $reference, $externalId, $paidOn, $confirmedBy, $note, $meta);
    }

    public static function failed(?string $note = null): self
    {
        return new self(PaymentStatus::Failed, note: $note);
    }

    public static function expired(?string $note = null): self
    {
        return new self(PaymentStatus::Expired, note: $note);
    }
}
