<?php

namespace Modules\Platform\App\Infrastructure\Navigation;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Platform\App\Contracts\TenantNavigation;

/**
 * In-memory navigation registry populated by each module's service
 * provider at boot; filtered by active module and Gate on read.
 */
final class DefaultTenantNavigation implements TenantNavigation
{
    /**
     * @var array<string, array{module: string, label: string, icon: string, group: ?string, route: string, permission: ?string, order: int, match: 'exact'|'prefix', children: list<array{label: string, route: string, permission: ?string, match: 'exact'|'prefix', shortcut: bool}>, shortcut: bool}>
     */
    private array $items = [];

    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantModules $modules,
    ) {}

    public function register(string $module, array $items): void
    {
        foreach ($items as $item) {
            $this->items[$item['route']] = [
                'module' => $module,
                'label' => $item['label'],
                'icon' => $item['icon'],
                'group' => $item['group'] ?? null,
                'route' => $item['route'],
                'permission' => $item['permission'] ?? null,
                'order' => $item['order'] ?? 100,
                'match' => $this->match($item),
                'shortcut' => ($item['shortcut'] ?? false) === true,
                'children' => array_map(fn (array $child): array => [
                    'label' => $child['label'],
                    'route' => $child['route'],
                    'permission' => $child['permission'] ?? null,
                    'match' => $this->match($child),
                    'shortcut' => ($child['shortcut'] ?? false) === true,
                ], array_values($item['children'] ?? [])),
            ];
        }
    }

    public function forCurrentUser(): array
    {
        if ($this->context->id() === null || Auth::guard()->guest()) {
            return [];
        }

        $visible = [];

        foreach ($this->items as $item) {
            if (! $this->modules->isEnabled($item['module']) || ! $this->allowed($item['permission'])) {
                continue;
            }

            $children = array_values(array_filter(
                $item['children'],
                fn (array $child): bool => $this->allowed($child['permission']),
            ));

            // A group whose every child is hidden has nowhere to go.
            if ($item['children'] !== [] && $children === []) {
                continue;
            }

            $visible[] = [...$item, 'children' => $children];
        }

        usort($visible, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return array_map(fn (array $item): array => [
            'label' => $item['label'],
            'icon' => $item['icon'],
            'group' => $item['group'],
            'href' => route($item['route'], absolute: false),
            'match' => $item['match'],
            'shortcut' => $item['shortcut'],
            'children' => array_map(fn (array $child): array => [
                'label' => $child['label'],
                'href' => route($child['route'], absolute: false),
                'match' => $child['match'],
                'shortcut' => $child['shortcut'],
            ], $item['children']),
        ], $visible);
    }

    private function allowed(?string $permission): bool
    {
        return $permission === null || Gate::allows($permission);
    }

    /**
     * @param  array{match?: 'exact'}  $item
     * @return 'exact'|'prefix'
     */
    private function match(array $item): string
    {
        return ($item['match'] ?? null) === 'exact' ? 'exact' : 'prefix';
    }
}
