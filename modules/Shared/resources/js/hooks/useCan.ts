import { usePage } from '@inertiajs/react';
import type { SharedPageProps } from '@inertiajs/core';

/**
 * Whether the signed-in user holds the given permission, as shared by
 * the server (`abilities`). Unknown names and signed-out users are false (fail
 * closed). Only hides UI; the server still refuses the request.
 */
export function useCan(): (permission: string) => boolean {
    const { abilities } = usePage<SharedPageProps>().props;

    return (permission) => abilities?.[permission] === true;
}
