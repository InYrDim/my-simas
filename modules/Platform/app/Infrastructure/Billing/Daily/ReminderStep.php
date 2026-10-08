<?php

namespace Modules\Platform\App\Infrastructure\Billing\Daily;

use Carbon\CarbonInterface;
use Modules\Platform\App\Domain\Models\BillingNoticeKind;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;
use Modules\Platform\App\Infrastructure\Billing\BillingNotifier;
use Throwable;

/**
 * The reminders a school's billing contact gets before and after a date it
 * must act on: a trial ending, an invoice falling due, an invoice that is
 * late while access still runs.
 *
 * Offsets are days from the anchor date (`billing.reminders`). A reminder
 * is due once its scheduled day has come and stays due only until the next
 * one takes over or its window closes, so a run that comes late sends the
 * newest reminder that still makes sense and never a stale one ("7 days
 * left" after the due date). The scheduled day is the notice's anchor date,
 * which is what keeps a second run on the same day from sending twice.
 */
final class ReminderStep
{
    public function __construct(
        private readonly BillingNotifier $notifier,
    ) {}

    public function run(CarbonInterface $today, bool $dryRun): DailyStepResult
    {
        $lines = [];
        $failures = [];

        $this->trialReminders($today, $dryRun, $lines, $failures);
        $this->invoiceReminders($today, $dryRun, $lines, $failures);

        return new DailyStepResult('reminders', count($lines), $lines, $failures);
    }

    /**
     * @param  list<string>  $lines
     * @param  list<string>  $failures
     */
    private function trialReminders(CarbonInterface $today, bool $dryRun, array &$lines, array &$failures): void
    {
        Subscription::query()
            ->with('tenant')
            ->where('status', SubscriptionStatus::Trial)
            ->whereNotNull('trial_ends_at')
            ->whereDoesntHave('invoices', fn ($invoices) => $invoices->where('status', InvoiceStatus::Unpaid))
            ->orderBy('id')
            ->each(function (Subscription $subscription) use ($today, $dryRun, &$lines, &$failures): void {
                $tenant = $subscription->tenant;
                $end = $subscription->trial_ends_at;

                if ($tenant === null || $tenant->billing_exempt || $end === null || $today->gt($end)) {
                    return;
                }

                $scheduled = $this->scheduled($end, (array) config('billing.reminders.trial_ending', [3]), -1, $today);

                if ($scheduled === null) {
                    return;
                }

                try {
                    if ($this->alreadySent(BillingNoticeKind::TrialEnding, $subscription, $scheduled)) {
                        return;
                    }

                    if (! $dryRun && $this->notifier->trialEnding($subscription, $scheduled, $end) === null) {
                        return;
                    }

                    $lines[] = "{$tenant->slug}: pengingat trial berakhir {$end->toDateString()}";
                } catch (Throwable $exception) {
                    report($exception);
                    $failures[] = "{$tenant->slug}: {$exception->getMessage()}";
                }
            });
    }

    /**
     * @param  list<string>  $lines
     * @param  list<string>  $failures
     */
    private function invoiceReminders(CarbonInterface $today, bool $dryRun, array &$lines, array &$failures): void
    {
        Invoice::query()
            ->with(['subscription', 'tenant'])
            ->where('status', InvoiceStatus::Unpaid)
            ->orderBy('id')
            ->each(function (Invoice $invoice) use ($today, $dryRun, &$lines, &$failures): void {
                $tenant = $invoice->tenant;
                $subscription = $invoice->subscription;

                if ($tenant === null || $tenant->billing_exempt || $subscription === null || $subscription->status === SubscriptionStatus::Cancelled) {
                    return;
                }

                try {
                    $line = $today->lte($invoice->due_at)
                        ? $this->dueReminder($invoice, $subscription, $today, $dryRun)
                        : $this->overdueReminder($invoice, $subscription, $today, $dryRun);

                    if ($line !== null) {
                        $lines[] = "{$tenant->slug}: {$line}";
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $failures[] = "{$tenant->slug}: {$exception->getMessage()}";
                }
            });
    }

    private function dueReminder(Invoice $invoice, Subscription $subscription, CarbonInterface $today, bool $dryRun): ?string
    {
        $scheduled = $this->scheduled($invoice->due_at, (array) config('billing.reminders.due', [7, 1]), -1, $today);

        // An invoice issued after the first reminder's day needs no reminder
        // on the day it was announced.
        if ($scheduled === null || $scheduled->lte($invoice->issued_at)) {
            return null;
        }

        if ($this->alreadySent(BillingNoticeKind::DueReminder, $subscription, $scheduled)) {
            return null;
        }

        if (! $dryRun && $this->notifier->dueReminder($invoice, $scheduled) === null) {
            return null;
        }

        return "pengingat jatuh tempo {$invoice->number}";
    }

    private function overdueReminder(Invoice $invoice, Subscription $subscription, CarbonInterface $today, bool $dryRun): ?string
    {
        // Once access has lapsed the school is closed, not reminded.
        if ($subscription->accessLapsed($today)) {
            return null;
        }

        $scheduled = $this->scheduled($invoice->due_at, (array) config('billing.reminders.overdue', [1, 5]), 1, $today);

        if ($scheduled === null || $this->alreadySent(BillingNoticeKind::OverdueReminder, $subscription, $scheduled)) {
            return null;
        }

        if (! $dryRun && $this->notifier->overdueReminder($invoice, $scheduled, $subscription->accessEndsAt()) === null) {
            return null;
        }

        return "pengingat terlambat {$invoice->number}";
    }

    /**
     * The newest scheduled day that has come: anchor shifted by each offset
     * (negative direction = before, positive = after), none later than
     * today.
     *
     * @param  array<int, int|string>  $offsets
     */
    private function scheduled(CarbonInterface $anchor, array $offsets, int $direction, CarbonInterface $today): ?CarbonInterface
    {
        $days = collect($offsets)
            ->map(fn ($offset): CarbonInterface => $anchor->copy()->addDays($direction * (int) $offset))
            ->filter(fn (CarbonInterface $day): bool => $day->lte($today))
            ->sortBy(fn (CarbonInterface $day): int => $day->getTimestamp());

        return $days->last();
    }

    private function alreadySent(BillingNoticeKind $kind, Subscription $subscription, CarbonInterface $scheduled): bool
    {
        return $this->notifier->wasRecorded($kind, $subscription->id, $scheduled);
    }
}
