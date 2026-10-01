export type TenantStatus = 'active' | 'suspended';
export type SubscriptionStatus =
    | 'trial'
    | 'active'
    | 'due'
    | 'overdue'
    | 'cancelled';
export type BillingCycle = 'monthly' | 'yearly';
export type InvoiceStatus = 'paid' | 'unpaid' | 'overdue' | 'void';

export interface ConsolePlan {
    key: string;
    label: string;
    priceMonthly: number;
    priceYearly: number;
    maxUsers: number | null;
    modules: string[];
    subscribers: number;
}

export interface ConsoleTenant {
    id: string;
    name: string;
    slug: string;
    domain: string | null;
    status: TenantStatus;
    timezone: string;
    plan: string;
    cycle: BillingCycle;
    subscriptionStatus: SubscriptionStatus;
    subscriptionStartedAt: string;
    renewsAt: string;
    userCount: number;
    createdAt: string;
    admin: { name: string; email: string };
    enabledModules: string[];
}

export interface ConsoleModule {
    key: string;
    label: string;
    available: boolean;
}

export interface ConsoleRole {
    key: string;
    label: string;
    permissions: string[];
}

export interface PermissionGroup {
    key: string;
    label: string;
    permissions: string[];
}

export interface ConsoleSubscription {
    tenantId: string;
    tenantName: string;
    slug: string;
    plan: string;
    cycle: BillingCycle;
    status: SubscriptionStatus;
    startedAt: string;
    endsAt: string;
    amount: number;
}

export interface ConsoleInvoice {
    number: string;
    tenantId: string;
    tenantName: string;
    plan: string;
    cycle: BillingCycle;
    amount: number;
    status: InvoiceStatus;
    issuedAt: string;
}

export interface TrendPoint {
    month: string;
    newTenants: number;
    revenue: number;
}

export interface ProviderUserRow {
    id: number;
    name: string;
    email: string;
    role: string;
    active: boolean;
    lastLoginAt: string;
}
