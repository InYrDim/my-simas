import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { PageHeader } from '@shared/components/page-parts';
import TenantShell from '@shared/components/TenantShell';

/** Frame for every Absensi page: tenant shell and title. */
export default function AttendancePage({
    title,
    description,
    actions,
    width = 'max-w-5xl',
    children,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
    width?: string;
    children: ReactNode;
}) {
    return (
        <TenantShell width={width}>
            <Head title={title} />

            <PageHeader title={title} description={description} actions={actions} />

            <div className="mt-8">{children}</div>
        </TenantShell>
    );
}
