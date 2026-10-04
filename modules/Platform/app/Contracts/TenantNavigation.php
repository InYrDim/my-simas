<?php

namespace Modules\Platform\App\Contracts;

/**
 * Registry of the school-side sidebar entries. Each module registers its
 * own entries from its own service provider — Platform never hardcodes
 * business navigation, and the tenant shell never imports a module.
 *
 * Entries are filtered per request: the owning module must be active for
 * the current tenant, and the optional permission must pass the Gate for
 * the signed-in user.
 *
 * Entry shape: label, icon (a lucide icon name in kebab-case, e.g.
 * "layout-dashboard"; the shell loads it lazily, so no frontend change is
 * needed per module), route (a named route), optional permission, optional
 * group (a section heading such as "Data Induk"; entries sharing one are
 * rendered together, sections ordered by their first entry; none = ungrouped),
 * optional order (lower first, default 100), optional match ("exact" for an index
 * that shares a prefix with sibling pages), optional shortcut (true = also offered as a
 * quick link on the signed-in user's Beranda), optional children (sub-pages:
 * label, route, optional permission, optional match, optional shortcut). A group shows only
 * while at least one child is visible.
 */
interface TenantNavigation
{
    /**
     * Register entries for the given module key. Called from the owning
     * module's service provider during boot. Idempotent per route name.
     *
     * @param  array<int, array{label: string, icon: string, group?: string, route: string, permission?: string, order?: int, match?: 'exact', shortcut?: bool, children?: array<int, array{label: string, route: string, permission?: string, match?: 'exact', shortcut?: bool}>}>  $items
     */
    public function register(string $module, array $items): void;

    /**
     * Entries visible to the current user in the current tenant, ordered,
     * with urls resolved. Empty without a tenant context or a signed-in
     * user.
     *
     * @return list<array{label: string, icon: string, group: ?string, href: string, match: 'exact'|'prefix', shortcut: bool, children: list<array{label: string, href: string, match: 'exact'|'prefix', shortcut: bool}>}>
     */
    public function forCurrentUser(): array;
}
