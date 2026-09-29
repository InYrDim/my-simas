import { usePage } from '@inertiajs/react';
import type { SharedPageProps } from '@inertiajs/core';

import type { Tenant, TenantModules } from '../types/tenant';

export type { Tenant, TenantModules };

/**
 * The current tenant as shared by the Platform module, or null on
 * central hosts. Reads only shared props — no Platform internals.
 */
export function useTenant(): Tenant | null {
    const { tenant } = usePage<SharedPageProps>().props;

    return tenant ?? null;
}

/**
 * The list of module keys active for the current tenant (empty on
 * central hosts). Reads only shared props — no Platform internals.
 */
export function useModules(): TenantModules {
    const { modules } = usePage<SharedPageProps>().props;

    return modules;
}

/**
 * Whether the given module key is active for the current tenant.
 * Unknown keys are always false (fail closed, matching the backend).
 */
export function hasModule(key: string): boolean {
    const { modules } = usePage<SharedPageProps>().props;

    return modules.includes(key);
}
