import { router } from '@inertiajs/react';

import { consolePath } from './consolePath';

type Method = 'post' | 'put';

/**
 * Console write helper: submits to a Wayfinder URL on the console host and
 * keeps the scroll position, so the page updates in place with its flash.
 */
export function send(
    method: Method,
    url: string,
    data: Record<string, unknown> = {},
): void {
    router[method](consolePath(url), data as never, { preserveScroll: true });
}

/** Re-query a list page with new filters, keeping typed input intact. */
export function applyFilters(
    url: string,
    filters: Record<string, string>,
): void {
    const params = Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== ''),
    );

    router.get(consolePath(url), params, {
        preserveState: true,
        replace: true,
    });
}
