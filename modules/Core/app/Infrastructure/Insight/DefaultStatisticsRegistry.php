<?php

namespace Modules\Core\App\Infrastructure\Insight;

use Illuminate\Contracts\Container\Container;
use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\StatisticsProvider;
use Modules\Core\App\Contracts\StatisticsRegistry;
use Modules\Platform\App\Contracts\TenantModules;

/**
 * In-memory statistics registry populated by each module's service
 * provider at boot. The read side is Core's own: it asks the providers of
 * the modules active for the current tenant, in registration order, and
 * appends the announced entries nobody provides yet (`available: false`).
 */
final class DefaultStatisticsRegistry implements StatisticsRegistry
{
    /**
     * @var array<class-string<StatisticsProvider>, string> provider class => module key
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
     * @return list<array{key: string, label: string, value: int|float|string|null, hint: ?string, available: bool}>
     */
    public function figures(?ReportPeriod $period): array
    {
        $figures = [];

        foreach ($this->providers() as $provider) {
            foreach ($provider->figures($period) as $figure) {
                $figures[$figure->key] = [
                    'key' => $figure->key,
                    'label' => $figure->label,
                    'value' => $figure->value,
                    'hint' => $figure->hint,
                    'available' => true,
                ];
            }
        }

        /** @var list<array{key: string, label: string}> $upcoming */
        $upcoming = config('insight.upcoming.figures', []);

        foreach ($upcoming as $figure) {
            $figures[$figure['key']] ??= [...$figure, 'value' => null, 'hint' => null, 'available' => false];
        }

        return array_values($figures);
    }

    /**
     * @return list<array{key: string, title: string, kind: ?string, points: list<array{label: string, value: int|float}>, note: ?string, available: bool}>
     */
    public function panels(?ReportPeriod $period): array
    {
        $panels = [];

        foreach ($this->providers() as $provider) {
            foreach ($provider->panels($period) as $panel) {
                $panels[$panel->key] = [
                    'key' => $panel->key,
                    'title' => $panel->title,
                    'kind' => $panel->kind,
                    'points' => $panel->points,
                    'note' => $panel->note,
                    'available' => true,
                ];
            }
        }

        /** @var list<array{key: string, title: string}> $upcoming */
        $upcoming = config('insight.upcoming.panels', []);

        foreach ($upcoming as $panel) {
            $panels[$panel['key']] ??= [...$panel, 'kind' => null, 'points' => [], 'note' => null, 'available' => false];
        }

        return array_values($panels);
    }

    /**
     * @return list<StatisticsProvider>
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
