/**
 * Shape of the tenant shared prop. Declared here (Shared) so the hook
 * and the types live together; the root resources/js/types re-exports
 * this for the glue layer.
 */
export type Tenant = {
    name: string;
    slug: string;
    timezone: string;
};

export type TenantModules = string[];
