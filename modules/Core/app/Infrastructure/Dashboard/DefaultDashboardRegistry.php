<?php

namespace Modules\Core\App\Infrastructure\Dashboard;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Gate;
use Modules\Core\App\Contracts\DashboardRegistry;
use Modules\Core\App\Contracts\DashboardWidgetProvider;
use Modules\Core\App\Contracts\DTOs\DashboardWidget;
use Modules\Platform\App\Contracts\TenantModules;
use Throwable;

/**
 * In-memory dashboard registry populated by each module's service
 * provider at boot. The read side is Core's own: it asks the providers of
 * the modules active for the current tenant, keeps the widgets the
 * signed-in user may see, and groups them by slot in `order`.
 *
 * One module failing must not take the landing page down: a provider that
 * throws is reported and skipped.
 */
final class DefaultDashboardRegistry implements DashboardRegistry
{
    /**
     * @var array<class-string<DashboardWidgetProvider>, string> provider class => module key
     */
    private array $modules = [];

    public function __construct(
        private readonly Container $container,
        private readonly TenantModules $tenantModules,
    ) {}

    public function register(string $module, string $provider): void
    {
        $this->modules[$provider] = $module;
    }

    /**
     * @return array<string, list<array{key: string, kind: string, title: string, payload: array<string, mixed>, href: ?string}>>
     */
    public function widgetsForCurrentUser(): array
    {
        $visible = [];

        foreach ($this->providers() as $provider) {
            try {
                $widgets = $provider->widgets();
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            foreach ($widgets as $widget) {
                if ($widget->permission === null || Gate::allows($widget->permission)) {
                    $visible[] = $widget;
                }
            }
        }

        usort($visible, fn (DashboardWidget $a, DashboardWidget $b): int => $a->order <=> $b->order);

        $slots = [];

        foreach ($visible as $widget) {
            $slots[$widget->slot][] = [
                'key' => $widget->key,
                'kind' => $widget->kind,
                'title' => $widget->title,
                'payload' => $this->capped($widget),
                'href' => $widget->href,
            ];
        }

        return $slots;
    }

    /**
     * A list on the Beranda is a glance: at most MAX_ITEMS rows, with the
     * real count in `total` so the page can link to the full list.
     *
     * @return array<string, mixed>
     */
    private function capped(DashboardWidget $widget): array
    {
        $items = $widget->payload['items'] ?? null;

        if ($widget->kind !== DashboardWidget::KIND_LIST || ! is_array($items)) {
            return $widget->payload;
        }

        return [
            ...$widget->payload,
            'items' => array_slice(array_values($items), 0, DashboardWidget::MAX_ITEMS),
            'total' => $widget->payload['total'] ?? count($items),
        ];
    }

    /**
     * @return list<DashboardWidgetProvider>
     */
    private function providers(): array
    {
        $providers = [];

        foreach ($this->modules as $class => $module) {
            if ($this->tenantModules->isEnabled($module)) {
                $providers[] = $this->container->make($class);
            }
        }

        return $providers;
    }
}
