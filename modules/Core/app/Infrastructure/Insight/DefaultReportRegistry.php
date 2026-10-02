<?php

namespace Modules\Core\App\Infrastructure\Insight;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Gate;
use Modules\Core\App\Contracts\Report;
use Modules\Core\App\Contracts\ReportRegistry;
use Modules\Platform\App\Contracts\TenantModules;

/**
 * In-memory report registry populated by each module's service provider
 * at boot. The read side (catalogue, find) is Core's own and filters by
 * active module and Gate on every call.
 */
final class DefaultReportRegistry implements ReportRegistry
{
    /**
     * @var array<class-string<Report>, string> report class => module key
     */
    private array $modules = [];

    /**
     * @var array<class-string<Report>, Report>
     */
    private array $resolved = [];

    public function __construct(
        private readonly Container $container,
        private readonly TenantModules $tenantModules,
    ) {}

    public function register(string $module, string $report): void
    {
        $this->modules[$report] = $module;
    }

    /**
     * The catalogue for the signed-in user: the reports they may download,
     * followed in each group by the announced ones nobody has registered
     * yet (`available: false`). Groups are ordered by their first entry.
     *
     * @return list<array{title: string, reports: list<array{key: string, name: string, description: string, available: bool}>}>
     */
    public function catalogue(): array
    {
        $entries = [];
        $registeredKeys = [];

        foreach ($this->modules as $class => $module) {
            $definition = $this->resolve($class)->definition();
            $registeredKeys[] = $definition->key;

            if (! $this->tenantModules->isEnabled($module) || ! $this->allowed($definition->permission)) {
                continue;
            }

            $entries[] = [
                'group' => $definition->group,
                'order' => $definition->order,
                'key' => $definition->key,
                'name' => $definition->name,
                'description' => $definition->description,
                'available' => true,
            ];
        }

        /** @var list<array{key: string, group: string, name: string, description: string, order: int}> $upcoming */
        $upcoming = config('insight.upcoming.reports', []);

        foreach ($upcoming as $report) {
            if (! in_array($report['key'], $registeredKeys, true)) {
                $entries[] = [...$report, 'available' => false];
            }
        }

        usort($entries, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        $groups = [];

        foreach ($entries as $entry) {
            $groups[$entry['group']] ??= ['title' => $entry['group'], 'reports' => []];
            $groups[$entry['group']]['reports'][] = [
                'key' => $entry['key'],
                'name' => $entry['name'],
                'description' => $entry['description'],
                'available' => $entry['available'],
            ];
        }

        return array_values($groups);
    }

    /**
     * The report with this key, when its module is active for the current
     * tenant. The permission of its definition is the caller's to check.
     */
    public function find(string $key): ?Report
    {
        foreach ($this->modules as $class => $module) {
            $report = $this->resolve($class);

            if ($report->definition()->key === $key) {
                return $this->tenantModules->isEnabled($module) ? $report : null;
            }
        }

        return null;
    }

    /**
     * @param  class-string<Report>  $class
     */
    private function resolve(string $class): Report
    {
        return $this->resolved[$class] ??= $this->container->make($class);
    }

    private function allowed(?string $permission): bool
    {
        return $permission === null || Gate::allows($permission);
    }
}
