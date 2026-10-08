<?php

namespace Modules\Platform\App\Infrastructure\Billing;

use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Platform\App\Domain\Exceptions\BillingException;
use Modules\Platform\App\Domain\Models\BillingNotice;
use Modules\Platform\App\Domain\Models\BillingNoticeKind;
use Modules\Platform\App\Domain\Models\BillingNoticeStatus;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Mail\AccessReopenedMail;
use Modules\Platform\App\Infrastructure\Mail\AccessStoppedMail;
use Modules\Platform\App\Infrastructure\Mail\BillingMail;
use Modules\Platform\App\Infrastructure\Mail\DueReminderMail;
use Modules\Platform\App\Infrastructure\Mail\InvoiceIssuedMail;
use Modules\Platform\App\Infrastructure\Mail\OverdueReminderMail;
use Modules\Platform\App\Infrastructure\Mail\PaymentReceivedMail;

/**
 * Sends the six billing messages to a school's billing contact. One
 * method per kind; each writes a billing_notices row first, then queues
 * the mail once the surrounding transaction has committed (a rolled-back
 * invoice or payment never announces itself). A school without a
 * billing_email gets a `failed` notice and nothing is queued: a missing
 * contact never breaks the invoice or the payment.
 *
 * Scheduled kinds pass an anchor date; a second call for the same
 * subscription, kind and anchor date is a no-op (returns null), which is
 * what makes the daily job safe to run twice.
 */
final class BillingNotifier
{
    public const NO_CONTACT = 'Kontak tagihan belum diisi.';

    public function invoiceIssued(Invoice $invoice): ?BillingNotice
    {
        return $this->notifyInvoice(BillingNoticeKind::InvoiceIssued, $invoice, null, InvoiceIssuedMail::class);
    }

    public function dueReminder(Invoice $invoice, CarbonInterface $anchorDate): ?BillingNotice
    {
        return $this->notifyInvoice(BillingNoticeKind::DueReminder, $invoice, $anchorDate, DueReminderMail::class);
    }

    public function overdueReminder(Invoice $invoice, CarbonInterface $anchorDate, ?CarbonInterface $graceEndsOn = null): ?BillingNotice
    {
        return $this->notifyInvoice(
            BillingNoticeKind::OverdueReminder,
            $invoice,
            $anchorDate,
            OverdueReminderMail::class,
            ['graceEndsOn' => $graceEndsOn?->format('d/m/Y')],
        );
    }

    public function paymentReceived(Invoice $invoice): ?BillingNotice
    {
        return $this->notifyInvoice(BillingNoticeKind::PaymentReceived, $invoice, null, PaymentReceivedMail::class);
    }

    public function accessStopped(Subscription $subscription, ?Invoice $invoice, CarbonInterface $anchorDate): ?BillingNotice
    {
        return $this->notifySubscription(BillingNoticeKind::AccessStopped, $subscription, $invoice, $anchorDate, AccessStoppedMail::class);
    }

    public function accessReopened(Subscription $subscription, ?Invoice $invoice = null): ?BillingNotice
    {
        return $this->notifySubscription(BillingNoticeKind::AccessReopened, $subscription, $invoice, null, AccessReopenedMail::class);
    }

    /**
     * Re-queue the message that fits the invoice's state: the invoice for
     * an unpaid one, the receipt for a paid one. Always recorded, never
     * deduplicated.
     *
     * @throws BillingException
     */
    public function resend(Invoice $invoice): BillingNotice
    {
        $notice = match ($invoice->status) {
            InvoiceStatus::Unpaid => $this->invoiceIssued($invoice),
            InvoiceStatus::Paid => $this->paymentReceived($invoice),
            InvoiceStatus::Void => throw new BillingException("Invoice [{$invoice->number}] is void and cannot be sent."),
        };

        return $notice ?? throw new BillingException('The notice could not be recorded.');
    }

    /**
     * @param  class-string<BillingMail>  $mailClass
     * @param  array<string, mixed>  $extra  extra constructor values for the mail
     */
    private function notifyInvoice(BillingNoticeKind $kind, Invoice $invoice, ?CarbonInterface $anchorDate, string $mailClass, array $extra = []): ?BillingNotice
    {
        return $this->record($kind, $invoice->tenant_id, $invoice->subscription_id, $invoice, $anchorDate, $mailClass, $extra);
    }

    /**
     * @param  class-string<BillingMail>  $mailClass
     */
    private function notifySubscription(BillingNoticeKind $kind, Subscription $subscription, ?Invoice $invoice, ?CarbonInterface $anchorDate, string $mailClass): ?BillingNotice
    {
        return $this->record($kind, $subscription->tenant_id, $subscription->id, $invoice, $anchorDate, $mailClass, []);
    }

    /**
     * @param  class-string<BillingMail>  $mailClass
     * @param  array<string, mixed>  $extra
     */
    private function record(BillingNoticeKind $kind, string $tenantId, ?int $subscriptionId, ?Invoice $invoice, ?CarbonInterface $anchorDate, string $mailClass, array $extra): ?BillingNotice
    {
        if ($anchorDate !== null && $this->alreadyRecorded($kind, $subscriptionId, $anchorDate)) {
            return null;
        }

        $tenant = Tenant::withTrashed()->find($tenantId);
        $recipient = trim((string) $tenant?->billing_email);

        try {
            $notice = BillingNotice::query()->create([
                'tenant_id' => $tenantId,
                'subscription_id' => $subscriptionId,
                'invoice_id' => $invoice?->id,
                'kind' => $kind,
                'channel' => 'email',
                'recipient' => $recipient !== '' ? $recipient : null,
                'status' => $recipient !== '' ? BillingNoticeStatus::Queued : BillingNoticeStatus::Failed,
                'anchor_date' => $anchorDate?->toDateString(),
                'error' => $recipient !== '' ? null : self::NO_CONTACT,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        if ($recipient === '') {
            return $notice;
        }

        $mail = $this->mail($mailClass, $tenant, $invoice, $notice, $extra);

        DB::afterCommit(static function () use ($recipient, $mail): void {
            Mail::to($recipient)->queue($mail);
        });

        return $notice;
    }

    private function alreadyRecorded(BillingNoticeKind $kind, ?int $subscriptionId, CarbonInterface $anchorDate): bool
    {
        return BillingNotice::query()
            ->where('subscription_id', $subscriptionId)
            ->where('kind', $kind)
            ->whereDate('anchor_date', $anchorDate->toDateString())
            ->exists();
    }

    /**
     * @param  class-string<BillingMail>  $mailClass
     * @param  array<string, mixed>  $extra
     */
    private function mail(string $mailClass, ?Tenant $tenant, ?Invoice $invoice, BillingNotice $notice, array $extra): BillingMail
    {
        $issuer = (array) config('billing.issuer', []);
        $contact = collect([$issuer['email'] ?? null, filled($issuer['whatsapp'] ?? null) ? 'WhatsApp '.$issuer['whatsapp'] : null])
            ->filter()
            ->implode(' / ');

        return new $mailClass(...[
            'recipientName' => $tenant?->billing_name ?: ($tenant?->name ?? ''),
            'schoolName' => $tenant?->name ?? '',
            'noticeId' => $notice->id,
            'issuerName' => (string) ($issuer['name'] ?? 'SIMAS'),
            'issuerContact' => $contact !== '' ? $contact : null,
            'invoiceId' => $invoice?->id,
            'invoiceNumber' => $invoice?->number,
            'amount' => $invoice === null ? null : 'Rp '.number_format($invoice->amount, 0, ',', '.'),
            'dueOn' => $invoice?->due_at->format('d/m/Y'),
            'periodLabel' => $invoice === null ? null : $invoice->period_start->format('d/m/Y').' - '.$invoice->period_end->format('d/m/Y'),
            ...$extra,
        ]);
    }

    /**
     * Marks a notice sent when its mail has left the application. Wired to
     * the MessageSent event in the Platform provider.
     */
    public static function markSent(int $noticeId): void
    {
        BillingNotice::query()
            ->whereKey($noticeId)
            ->where('status', BillingNoticeStatus::Queued)
            ->update(['status' => BillingNoticeStatus::Sent, 'sent_at' => now()]);
    }
}
