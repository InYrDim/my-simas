export type TenantStatus = 'active' | 'suspended';
export type SubscriptionStatus = 'trial' | 'active' | 'cancelled';
export type SubscriptionState =
    | 'trial'
    | 'trial_expired'
    | 'active'
    | 'due'
    | 'overdue'
    | 'cancelled';
export type BillingCycle = 'monthly' | 'yearly';
export type InvoiceStatus = 'paid' | 'unpaid' | 'void';
export type InvoiceState = InvoiceStatus | 'overdue';

export interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

/** A plan's ceilings; null = no limit. */
export interface PlanLimits {
    students: number | null;
    staffAccounts: number | null;
    storageMb: number | null;
}

export interface ConsolePlan {
    id: number;
    key: string;
    name: string;
    priceMonthly: number;
    priceYearly: number;
    limits: PlanLimits;
    modules: string[];
    isActive: boolean;
    isPublic: boolean;
    sortOrder: number;
    archived: boolean;
    subscribers: number | null;
}

export interface PlanOption {
    key: string;
    name: string;
}

export interface ConsoleSubscription {
    id: number;
    tenantId: string;
    tenantName: string | null;
    tenantSlug: string | null;
    planKey: string | null;
    planName: string | null;
    cycle: BillingCycle;
    status: SubscriptionStatus;
    state: SubscriptionState;
    trialEndsAt: string | null;
    periodStart: string | null;
    periodEnd: string | null;
    endsAt: string | null;
    amount: number | null;
}

export interface ConsoleInvoice {
    id: number;
    number: string;
    tenantId: string;
    tenantName: string | null;
    planName: string;
    cycle: BillingCycle;
    amount: number;
    status: InvoiceStatus;
    state: InvoiceState;
    issuedAt: string;
    dueAt: string;
    paidAt: string | null;
    periodStart: string;
    periodEnd: string;
}

export interface TenantListItem {
    id: string;
    name: string;
    slug: string;
    status: TenantStatus;
    timezone: string;
    createdAt: string | null;
    subscription: ConsoleSubscription | null;
}

export interface TenantDetail {
    id: string;
    name: string;
    slug: string;
    domain: string | null;
    status: TenantStatus;
    timezone: string;
    createdAt: string | null;
}

export interface ConsoleModule {
    key: string;
    label: string;
    alwaysActive: boolean;
    enabled: boolean;
    locked: boolean;
    inPlan: boolean;
}

export interface ModuleOption {
    key: string;
    label: string;
    alwaysActive: boolean;
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

export type WhatsappInstanceStatus =
    | 'pending'
    | 'active'
    | 'rejected'
    | 'disabled';

export interface ConsoleWhatsappInstance {
    id: number;
    tenantId: string;
    tenantName: string | null;
    tenantSlug: string | null;
    status: WhatsappInstanceStatus;
    requestedAt: string | null;
    decidedAt: string | null;
    note: string | null;
    sessionName: string | null;
    /** The gateway's session status as last seen (`created`, `ready`, ...). */
    connection: string | null;
    phone: string | null;
    lastError: string | null;
}

export interface TrendPoint {
    month: string;
    newTenants: number;
    revenue: number;
}
