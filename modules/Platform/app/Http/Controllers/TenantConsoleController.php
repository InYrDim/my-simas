<?php

namespace Modules\Platform\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\PermissionRegistry;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Platform\App\Contracts\TenantRoles;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantStatus;
use Modules\Platform\App\Http\Support\ConsoleResources;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\App\Infrastructure\Tenancy\TenantLifecycle;

/**
 * Provider console tenant management: list, detail, profile edit,
 * suspend/activate and module flags. Subscription actions live in
 * TenantSubscriptionController.
 */
final class TenantConsoleController
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly TenantModules $tenantModules,
        private readonly ModuleFlagManager $flags,
        private readonly TenantLifecycle $lifecycle,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(TenantStatus::class)],
            'plan' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', Rule::in(['trial', 'subscribed', 'cancelled', 'none'])],
        ]);

        $tenants = Tenant::query()
            ->with('subscription.plan')
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('id', 'like', $like));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['plan'] ?? null, fn ($query, string $plan) => $query->whereHas(
                'subscription.plan',
                fn ($inner) => $inner->where('key', $plan),
            ))
            ->when($filters['mode'] ?? null, function ($query, string $mode): void {
                match ($mode) {
                    'trial' => $query->whereHas('subscription', fn ($s) => $s->where('status', SubscriptionStatus::Trial)),
                    'subscribed' => $query->whereHas('subscription', fn ($s) => $s->where('status', SubscriptionStatus::Active)),
                    'cancelled' => $query->whereHas('subscription', fn ($s) => $s->where('status', SubscriptionStatus::Cancelled)),
                    default => $query->whereDoesntHave('subscription'),
                };
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status->value,
                'timezone' => $tenant->timezone,
                'createdAt' => $tenant->created_at?->toDateString(),
                'subscription' => $tenant->subscription !== null
                    ? ConsoleResources::subscription($tenant->subscription)
                    : null,
            ]);

        return Inertia::render('Platform/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? '',
                'plan' => $filters['plan'] ?? '',
                'mode' => $filters['mode'] ?? '',
            ],
            'plans' => Plan::query()->orderBy('sort_order')->get(['key', 'name']),
        ]);
    }

    public function show(Tenant $tenant, TenantRoles $roles, PermissionRegistry $permissions): Response
    {
        $subscription = Subscription::query()->with(['plan', 'tenant'])->where('tenant_id', $tenant->id)->first();
        $planModules = $subscription?->plan?->modules ?? [];
        $protected = (array) config('tenancy.onboarding_modules', []);

        $modules = collect($this->registry->all())->map(fn (array $meta, string $key): array => [
            'key' => $key,
            'label' => $meta['label'] ?? $key,
            'alwaysActive' => $this->registry->isAlwaysActive($key),
            'enabled' => $this->tenantModules->isEnabled($key, $tenant->id),
            'locked' => $this->registry->isAlwaysActive($key) || in_array($key, $protected, true),
            'inPlan' => in_array($key, $planModules, true),
        ])->values();

        $catalog = collect($permissions->all())->map(fn (array $names, string $module): array => [
            'key' => $module,
            'label' => $this->registry->all()[$module]['label'] ?? $module,
            'permissions' => array_values($names),
        ])->values();

        $roleRows = collect($roles->rolePermissions($tenant->id))->map(fn (array $names, string $role): array => [
            'key' => $role,
            'label' => ucfirst(str_replace('-', ' ', $role)),
            'permissions' => $names,
        ])->values();

        return Inertia::render('Platform/Tenants/Show', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $tenant->domain,
                'status' => $tenant->status->value,
                'timezone' => $tenant->timezone,
                'createdAt' => $tenant->created_at?->toDateString(),
            ],
            'subscription' => $subscription !== null ? ConsoleResources::subscription($subscription) : null,
            'plans' => Plan::query()->selectable()->orderBy('sort_order')->get()
                ->map(fn (Plan $plan): array => ConsoleResources::plan($plan))->all(),
            'modules' => $modules,
            'permissionCatalog' => $catalog,
            'roles' => $roleRows,
            'invoices' => Invoice::query()->with('tenant')->where('tenant_id', $tenant->id)
                ->orderByDesc('id')->limit(20)->get()
                ->map(fn (Invoice $invoice): array => ConsoleResources::invoice($invoice))->all(),
            'trialDays' => (int) config('billing.trial_days', 14),
        ]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->merge(['domain' => mb_strtolower(trim((string) $request->input('domain')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'timezone:all'],
            'domain' => [
                'nullable', 'string', 'max:190', 'regex:/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/',
                Rule::unique('tenants', 'domain')->ignore($tenant->id, 'id'),
                Rule::notIn([config('tenancy.console_domain')]),
            ],
        ]);

        $this->lifecycle->updateProfile($tenant, [
            'name' => trim($data['name']),
            'timezone' => $data['timezone'],
            'domain' => filled($data['domain'] ?? null) ? $data['domain'] : null,
        ]);

        return back()->with('status', 'Data tenant diperbarui.');
    }

    public function suspend(Tenant $tenant): RedirectResponse
    {
        $this->lifecycle->suspend($tenant);

        return back()->with('status', "{$tenant->name} ditangguhkan.");
    }

    public function activate(Tenant $tenant): RedirectResponse
    {
        $this->lifecycle->activate($tenant);

        return back()->with('status', "{$tenant->name} diaktifkan kembali.");
    }

    /**
     * Set the tenant's module flags to exactly the submitted set. Always-
     * active and onboarding modules (login depends on them) are kept on.
     */
    public function syncModules(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'modules' => ['array'],
            'modules.*' => ['string', Rule::in(array_keys($this->registry->all()))],
        ]);

        $wanted = array_merge($data['modules'] ?? [], (array) config('tenancy.onboarding_modules', []));

        foreach (array_keys($this->registry->all()) as $key) {
            if ($this->registry->isAlwaysActive($key)) {
                continue;
            }

            in_array($key, $wanted, true)
                ? $this->flags->enable($tenant->id, $key)
                : $this->flags->disable($tenant->id, $key);
        }

        return back()->with('status', 'Modul tenant disimpan.');
    }
}
