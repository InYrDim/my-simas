<?php

namespace Modules\Platform\App\Infrastructure\Billing\Daily;

use Modules\Platform\App\Domain\Models\Payment;
use Modules\Platform\App\Domain\Models\PaymentStatus;
use Modules\Platform\App\Infrastructure\Billing\PaymentOutcome;
use Modules\Platform\App\Infrastructure\Billing\SubscriptionManager;
use Throwable;

/**
 * Closes the gateway payments that were never completed: a pending one
 * whose `expires_at` has passed becomes `expired`, and its invoice stays
 * unpaid so the school can try again. A manual transfer has no expiry and
 * is never touched here.
 */
final class PaymentExpiryStep
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
    ) {}

    public function run(bool $dryRun): DailyStepResult
    {
        $lines = [];
        $failures = [];

        Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->each(function (Payment $payment) use ($dryRun, &$lines, &$failures): void {
                try {
                    if (! $dryRun) {
                        $this->subscriptions->settle($payment, PaymentOutcome::expired());
                    }

                    $lines[] = "pembayaran #{$payment->id} kedaluwarsa";
                } catch (Throwable $exception) {
                    report($exception);
                    $failures[] = "pembayaran #{$payment->id}: {$exception->getMessage()}";
                }
            });

        return new DailyStepResult('payments', count($lines), $lines, $failures);
    }
}
