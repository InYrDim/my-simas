<?php

namespace Modules\Platform\App\Infrastructure\Billing;

use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\Plan;

/**
 * What a requested plan change turned into: it took effect at once (a
 * trial), an upgrade invoice is waiting for payment, or a downgrade was
 * scheduled for the end of the paid period.
 */
final readonly class PlanChangeResult
{
    private function __construct(
        public ?Invoice $upgradeInvoice,
        public ?Plan $scheduledPlan,
        public bool $immediate,
    ) {}

    public static function immediate(): self
    {
        return new self(null, null, true);
    }

    public static function upgrade(Invoice $invoice): self
    {
        return new self($invoice, null, false);
    }

    public static function scheduled(Plan $plan): self
    {
        return new self(null, $plan, false);
    }

    public function isImmediate(): bool
    {
        return $this->immediate;
    }

    public function isUpgrade(): bool
    {
        return $this->upgradeInvoice !== null;
    }

    public function isScheduled(): bool
    {
        return $this->scheduledPlan !== null;
    }
}
