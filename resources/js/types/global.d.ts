import type { Auth } from '@/types/auth';
import type { Tenant, TenantModules } from '@/types/tenant';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            tenant: Tenant | null;
            modules: TenantModules;
            abilities: Record<string, boolean>;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
