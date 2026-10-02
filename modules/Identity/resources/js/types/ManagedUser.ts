export interface ManagedUser {
    id: number;
    name: string;
    username: string | null;
    email: string | null;
    roles: string[];
    roleLabels: string[];
    isActive: boolean;
    hasPassword: boolean;
    emailVerifiedAt: string | null;
}
