export interface ManagedUser {
    id: number;
    name: string;
    email: string;
    roles: string[];
    roleLabels: string[];
    isActive: boolean;
    hasPassword: boolean;
    emailVerifiedAt: string | null;
}
