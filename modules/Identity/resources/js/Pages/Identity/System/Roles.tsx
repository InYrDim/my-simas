import { Head, Link } from '@inertiajs/react';
import { CheckIcon } from 'lucide-react';

import { index as usersIndex } from '@/actions/Modules/Identity/App/Http/Controllers/UsersManagementController';

import { PageHeader } from '@shared/components/page-parts';
import TenantShell from '@shared/components/TenantShell';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';

interface Role {
    name: string;
    label: string;
    description: string | null;
    userCount: number;
    permissions: string[];
}

/**
 * Peran: the roles that exist in this school, what each one may do and how
 * many accounts hold it. Read-only — roles are assigned per user.
 */
export default function Roles({ roles }: { roles: Role[] }) {
    return (
        <TenantShell>
            <Head title="Peran" />

            <PageHeader
                title="Peran"
                description="Peran yang tersedia di sekolah ini dan hak akses masing-masing. Peran ditetapkan di halaman ubah pengguna."
                actions={
                    <Button asChild variant="outline">
                        <Link href={usersIndex.url()}>Daftar pengguna</Link>
                    </Button>
                }
            />

            <ul className="mt-8 grid gap-4 md:grid-cols-2">
                {roles.map((role) => (
                    <li key={role.name}>
                        <Card className="h-full">
                            <CardHeader>
                                <div className="flex items-start justify-between gap-3">
                                    <CardTitle>{role.label}</CardTitle>
                                    <Badge variant={role.userCount > 0 ? 'default' : 'secondary'}>
                                        {role.userCount} pengguna
                                    </Badge>
                                </div>
                                <p className="font-mono text-xs text-muted-foreground">{role.name}</p>
                                {role.description !== null && (
                                    <CardDescription>{role.description}</CardDescription>
                                )}
                            </CardHeader>
                            <CardContent>
                                <h2 className="mb-2 text-xs font-medium text-muted-foreground">
                                    Hak akses
                                </h2>
                                {role.permissions.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada hak akses khusus.
                                    </p>
                                ) : (
                                    <ul className="flex flex-col gap-1.5 text-sm">
                                        {role.permissions.map((permission) => (
                                            <li key={permission} className="flex items-start gap-2">
                                                <CheckIcon className="mt-0.5 size-4 shrink-0 text-primary" />
                                                {permission}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </li>
                ))}
            </ul>
        </TenantShell>
    );
}
