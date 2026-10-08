<?php

namespace Modules\Platform\App\Contracts\DTOs;

/**
 * One invoice as the school sees it. Dates are `Y-m-d`, the amount is whole
 * rupiah. `status` is `unpaid`, `overdue` (unpaid past its due date),
 * `paid` or `void`. `instructions` is filled while a payment is waiting.
 */
final readonly class InvoiceSummary
{
    /**
     * @param  string  $kind  `activation`, `renewal` or `upgrade`
     */
    public function __construct(
        public string $number,
        public string $kind,
        public string $status,
        public string $planName,
        public string $cycle,
        public int $amount,
        public string $issuedOn,
        public string $dueOn,
        public string $periodStart,
        public string $periodEnd,
        public ?string $paidOn,
        public bool $payable,
        public ?PaymentInstructions $instructions = null,
    ) {}
}
