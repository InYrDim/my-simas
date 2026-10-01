<?php

namespace Modules\Platform\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Platform\App\Domain\Exceptions\BillingException;
use Modules\Platform\App\Domain\Models\BillingCycle;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Billing\SubscriptionManager;

/**
 * Provider console: manage one tenant's subscription (trial, plan,
 * cycle, renewal, cancellation). Rules live in SubscriptionManager; a
 * BillingException comes back as a `billing` form error.
 */
final class TenantSubscriptionController
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
    ) {}

    public function startTrial(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string'],
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        return $this->attempt(
            fn () => $this->subscriptions->startTrial($tenant->id, $data['plan'], $data['days'] ?? null),
            'Masa uji coba dimulai.',
        );
    }

    public function extendTrial(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:90']]);

        return $this->attempt(
            fn () => $this->subscriptions->extendTrial($this->subscriptionOf($tenant), $data['days']),
            'Masa uji coba diperpanjang.',
        );
    }

    public function activate(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string'],
            'cycle' => ['required', Rule::enum(BillingCycle::class)],
        ]);

        return $this->attempt(
            fn () => $this->subscriptions->activate(
                $this->subscriptionOf($tenant),
                $data['plan'],
                BillingCycle::from($data['cycle']),
            ),
            'Tagihan diterbitkan. Tandai lunas agar langganan aktif.',
        );
    }

    public function changePlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['plan' => ['required', 'string']]);

        return $this->attempt(
            fn () => $this->subscriptions->changePlan($this->subscriptionOf($tenant), $data['plan']),
            'Paket diganti.',
        );
    }

    public function changeCycle(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['cycle' => ['required', Rule::enum(BillingCycle::class)]]);

        return $this->attempt(
            fn () => $this->subscriptions->changeCycle($this->subscriptionOf($tenant), BillingCycle::from($data['cycle'])),
            'Siklus tagihan diubah (berlaku mulai tagihan berikutnya).',
        );
    }

    public function renew(Tenant $tenant): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->subscriptions->renew($this->subscriptionOf($tenant)),
            'Tagihan perpanjangan diterbitkan.',
        );
    }

    public function cancel(Tenant $tenant): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->subscriptions->cancel($this->subscriptionOf($tenant)),
            'Langganan dihentikan.',
        );
    }

    private function subscriptionOf(Tenant $tenant): Subscription
    {
        return Subscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (BillingException $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()]);
        }

        return back()->with('status', $success);
    }
}
