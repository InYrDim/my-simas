<?php

namespace Modules\Platform\App\Contracts\DTOs;

/**
 * How a school pays one invoice by bank transfer, and what it has already
 * told the provider about a transfer it made. The payment stays pending
 * until the provider confirms it.
 */
final readonly class PaymentInstructions
{
    /**
     * @param  array{transferredOn: string, bank: string, senderName: string, reference: string|null}|null  $reportedTransfer  what the school reported, if it did
     */
    public function __construct(
        public string $invoiceNumber,
        public int $amount,
        public ?string $bankName,
        public ?string $bankAccount,
        public ?string $accountHolder,
        public string $note,
        public ?array $reportedTransfer = null,
    ) {}
}
