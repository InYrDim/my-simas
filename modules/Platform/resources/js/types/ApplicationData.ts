export interface ApplicationData {
    id: number;
    schoolName: string;
    desiredSlug: string;
    timezone: string;
    applicantName: string;
    applicantEmail: string;
    applicantMessage: string | null;
    status: string;
    adminNote: string | null;
    decidedAt: string | null;
    decidedBy: number | null;
    applicantId: number | null;
    planKey: string | null;
    submittedAt: string | null;
}

/** A plan as offered to an applicant during onboarding. */
export interface PlanOption {
    key: string;
    name: string;
    priceMonthly: number;
    priceYearly: number;
    maxUsers: number | null;
    modules: string[];
}
