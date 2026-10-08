/**
 * The shared Inertia prop `billing` the account panels read. Platform
 * builds it (SchoolBillingPayload) and sends it only when a partial reload
 * asks for it; Shared only describes the shape, it imports nothing from
 * Platform. Dates are `Y-m-d`, money is whole rupiah.
 */

export type BillingState =
    | 'trial'
    | 'trial_expired'
    | 'active'
    | 'due'
    | 'overdue'
    | 'cancelled'
    | 'exempt'
    | 'none';

/**
 * The shared Inertia prop `trial`, present only while the school is on its
 * trial (`trial`) or just after it ended and access still runs
 * (`trial_expired`). `daysLeft` is negative once the trial is over.
 */
export interface TrialNotice {
    state: 'trial' | 'trial_expired';
    endsOn: string;
    daysLeft: number;
    accessEndsOn: string | null;
}

export type BillingCycleKey = 'monthly' | 'yearly';

export interface UsageLine {
    key: string;
    label: string;
    unit: string;
    used: number;
    limit: number | null;
    state: 'ok' | 'near' | 'over';
}

export interface BillingOverview {
    state: BillingState;
    planKey: string | null;
    planName: string | null;
    cycle: BillingCycleKey | null;
    price: number | null;
    trialEndsOn: string | null;
    periodStart: string | null;
    periodEnd: string | null;
    accessEndsOn: string | null;
    scheduledPlanKey: string | null;
    scheduledPlanName: string | null;
    modules: { key: string; label: string }[];
    usage: UsageLine[];
    canSubscribe: boolean;
    canChangePlan: boolean;
}

export interface PaymentInstructions {
    amount: number;
    bankName: string | null;
    bankAccount: string | null;
    accountHolder: string | null;
    note: string;
    reportedTransfer: {
        transferredOn: string;
        bank: string;
        senderName: string;
        reference: string | null;
    } | null;
}

export interface BillingInvoice {
    number: string;
    kind: 'activation' | 'renewal' | 'upgrade';
    status: 'unpaid' | 'overdue' | 'paid' | 'void';
    planName: string;
    cycle: BillingCycleKey;
    amount: number;
    issuedOn: string;
    dueOn: string;
    periodStart: string;
    periodEnd: string;
    paidOn: string | null;
    payable: boolean;
    instructions: PaymentInstructions | null;
    urls: { pay: string; reportTransfer: string; pdf: string } | null;
}

export interface PlanOffer {
    key: string;
    name: string;
    priceMonthly: number;
    priceYearly: number;
    limits: {
        students: number | null;
        staffAccounts: number | null;
        storageMb: number | null;
    };
    modules: { key: string; label: string }[];
    direction: 'current' | 'upgrade' | 'downgrade' | null;
    modulesLost: string[];
    overLimits: { label: string; unit: string; used: number; limit: number }[];
}

export interface SchoolBilling {
    canPay: boolean;
    overview: BillingOverview;
    invoices: BillingInvoice[];
    offers: PlanOffer[];
    urls: {
        subscribe: string;
        changePlan: string;
        cancelScheduledChange: string;
        changeCycle: string;
    } | null;
}
