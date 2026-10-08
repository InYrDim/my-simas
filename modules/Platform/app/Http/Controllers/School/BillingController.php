<?php

namespace Modules\Platform\App\Http\Controllers\School;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Modules\Platform\App\Contracts\DTOs\PlanChangeOutcome;
use Modules\Platform\App\Contracts\Exceptions\BillingActionRefusedException;
use Modules\Platform\App\Contracts\TenantBilling;
use Modules\Platform\App\Domain\Models\BillingCycle;

/**
 * The school's side of billing: the actions behind the "Paket &
 * Langganan" and "Tagihan & Invoice" panels. All the rules are in
 * TenantBilling; this only reads the request, turns a refusal into a form
 * error the panel can show, and answers an unknown invoice with a 404.
 *
 * Reading goes through the shared Inertia prop `billing`, not through
 * these routes.
 */
final class BillingController
{
    public function __construct(
        private readonly TenantBilling $billing,
    ) {}

    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', 'max:50'],
            'cycle' => ['required', Rule::enum(BillingCycle::class)],
        ]);

        return $this->attempt(function () use ($data): string {
            $invoice = $this->billing->subscribe($data['plan'], $data['cycle']);

            return "Invoice {$invoice->number} diterbitkan. Silakan bayar agar langganan aktif.";
        });
    }

    public function changePlan(Request $request): RedirectResponse
    {
        $data = $request->validate(['plan' => ['required', 'string', 'max:50']]);

        return $this->attempt(function () use ($data): string {
            $outcome = $this->billing->changePlan($data['plan']);

            return match ($outcome->kind) {
                PlanChangeOutcome::UPGRADE => "Invoice selisih {$outcome->invoice?->number} diterbitkan. Paket baru aktif setelah dibayar.",
                PlanChangeOutcome::SCHEDULED => "Penurunan ke paket {$outcome->scheduledPlanName} dijadwalkan saat periode berjalan berakhir.",
                default => 'Paket diganti.',
            };
        });
    }

    public function cancelScheduledChange(): RedirectResponse
    {
        return $this->attempt(function (): string {
            $this->billing->cancelScheduledChange();

            return 'Jadwal ganti paket dibatalkan.';
        });
    }

    public function changeCycle(Request $request): RedirectResponse
    {
        $data = $request->validate(['cycle' => ['required', Rule::enum(BillingCycle::class)]]);

        return $this->attempt(function () use ($data): string {
            $this->billing->changeCycle($data['cycle']);

            return 'Siklus pembayaran diubah mulai invoice berikutnya.';
        });
    }

    public function pay(string $invoice): RedirectResponse
    {
        return $this->attempt(function () use ($invoice): string {
            $this->billing->startPayment($invoice);

            return 'Instruksi pembayaran siap.';
        });
    }

    public function reportTransfer(Request $request, string $invoice): RedirectResponse
    {
        $data = $request->validate([
            'transferred_on' => ['required', 'date_format:Y-m-d'],
            'bank' => ['required', 'string', 'max:80'],
            'sender_name' => ['required', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:80'],
        ]);

        return $this->attempt(function () use ($invoice, $data): string {
            $this->billing->reportTransfer($invoice, $data['transferred_on'], $data['bank'], $data['sender_name'], $data['reference'] ?? null);

            return 'Terima kasih. Kami akan memeriksa transfer Anda dan mengonfirmasinya.';
        });
    }

    public function pdf(string $invoice): Response
    {
        try {
            $file = $this->billing->invoicePdf($invoice);
        } catch (BillingActionRefusedException $exception) {
            abort_if($exception->notFound, 404);

            throw $exception;
        }

        return response($file->contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$file->filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @param  callable(): string  $action  returns the message to flash
     */
    private function attempt(callable $action): RedirectResponse
    {
        try {
            $message = $action();
        } catch (BillingActionRefusedException $exception) {
            abort_if($exception->notFound, 404);

            return back()->withErrors(['billing' => $exception->getMessage()]);
        }

        return back()->with('status', $message);
    }
}
