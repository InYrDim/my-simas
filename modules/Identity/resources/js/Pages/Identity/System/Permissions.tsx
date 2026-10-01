import { Head } from '@inertiajs/react';
import { CheckIcon, MinusIcon } from 'lucide-react';

import { DataTable, PageHeader } from '@shared/components/page-parts';
import TenantShell from '@shared/components/TenantShell';
import { TableCell, TableRow } from '@shared/components/ui/table';

interface Role {
    name: string;
    label: string;
}

interface Group {
    module: string;
    label: string;
    permissions: { name: string; label: string; roles: string[] }[];
}

/**
 * Izin: every permission of the modules this school uses, and which roles
 * hold it. Read-only; grants change with the roles, not here.
 */
export default function Permissions({ roles, groups }: { roles: Role[]; groups: Group[] }) {
    return (
        <TenantShell width="max-w-5xl">
            <Head title="Izin" />

            <PageHeader
                title="Izin"
                description="Hal-hal yang dapat dilakukan di sekolah ini, dan peran mana yang diperbolehkan."
            />

            <div className="mt-8 flex flex-col gap-10">
                {groups.map((group) => (
                    <section key={group.module} aria-labelledby={`izin-${group.module}`}>
                        <h2 id={`izin-${group.module}`} className="mb-3 text-sm font-semibold">
                            {group.label}
                        </h2>
                        <DataTable head={['Izin', ...roles.map((role) => role.label)]}>
                            {group.permissions.map((permission) => (
                                <TableRow key={permission.name}>
                                    <TableCell>
                                        <p className="font-medium">{permission.label}</p>
                                        <p className="font-mono text-xs text-muted-foreground">
                                            {permission.name}
                                        </p>
                                    </TableCell>
                                    {roles.map((role) => (
                                        <TableCell key={role.name}>
                                            {permission.roles.includes(role.name) ? (
                                                <>
                                                    <CheckIcon className="size-4 text-primary" aria-hidden />
                                                    <span className="sr-only">Diperbolehkan</span>
                                                </>
                                            ) : (
                                                <>
                                                    <MinusIcon className="size-4 text-muted-foreground" aria-hidden />
                                                    <span className="sr-only">Tidak</span>
                                                </>
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </DataTable>
                    </section>
                ))}
            </div>
        </TenantShell>
    );
}
