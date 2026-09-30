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
}
