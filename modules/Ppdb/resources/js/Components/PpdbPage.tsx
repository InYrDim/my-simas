import { Head } from '@inertiajs/react';
import { FlaskConicalIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { PageHeader } from '@shared/components/page-parts';
import TenantShell from '@shared/components/TenantShell';
import { Alert, AlertDescription } from '@shared/components/ui/alert';

/** Frame for every PPDB page: tenant shell, title and the mock notice. */
export default function PpdbPage({
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

            <Alert className="mb-6">
                <FlaskConicalIcon />
                <AlertDescription>
                    Tampilan contoh — data belum tersimpan.
                </AlertDescription>
            </Alert>

            <PageHeader title={title} description={description} actions={actions} />

            <div className="mt-8">{children}</div>
        </TenantShell>
    );
}
