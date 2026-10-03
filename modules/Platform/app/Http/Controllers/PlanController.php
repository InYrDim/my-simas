<?php

namespace Modules\Platform\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Http\Support\ConsoleResources;

/**
 * Provider console plan catalogue (kelola paket). Plans are archived,
 * never deleted: existing subscriptions keep pointing at them.
 */
final class PlanController
{
    public function __construct(
        private readonly ModuleRegistry $registry,
    ) {}

    public function index(): Response
    {
        $plans = Plan::query()->withCount('subscriptions')->orderBy('sort_order')->orderBy('id')->get();

        return Inertia::render('Platform/Billing/Plans', [
            'plans' => $plans
                ->map(fn (Plan $plan): array => ConsoleResources::plan($plan, $plan->subscriptions_count))
                ->all(),
            'modules' => $this->moduleOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('plans', 'key')],
            ...$this->rules(),
        ]);

        Plan::query()->create($this->attributes($data) + ['key' => $data['key']]);

        return to_route('platform.billing.plans')->with('status', "Paket {$data['name']} dibuat.");
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $plan->update($this->attributes($data));

        return to_route('platform.billing.plans')->with('status', "Paket {$plan->name} diperbarui.");
    }

    public function archive(Plan $plan): RedirectResponse
    {
        $plan->forceFill(['archived_at' => now(), 'is_active' => false])->save();

        return back()->with('status', "Paket {$plan->name} diarsipkan.");
    }

    public function restore(Plan $plan): RedirectResponse
    {
        $plan->forceFill(['archived_at' => null, 'is_active' => true])->save();

        return back()->with('status', "Paket {$plan->name} dipulihkan.");
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'price_monthly' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'price_yearly' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'max_users' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'modules' => ['array'],
            'modules.*' => ['string', Rule::in(array_keys($this->registry->all()))],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'price_monthly' => $data['price_monthly'],
            'price_yearly' => $data['price_yearly'],
            'max_users' => $data['max_users'] ?? null,
            'modules' => array_values(array_unique(['core', ...($data['modules'] ?? [])])),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    /**
     * @return list<array{key: string, label: string, alwaysActive: bool}>
     */
    private function moduleOptions(): array
    {
        return array_values(collect($this->registry->all())
            ->map(fn (array $meta, string $key): array => [
                'key' => $key,
                'label' => $meta['label'] ?? $key,
                'alwaysActive' => $this->registry->isAlwaysActive($key),
            ])
            ->all());
    }
}
