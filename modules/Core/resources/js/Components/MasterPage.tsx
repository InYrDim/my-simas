import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronLeftIcon, FlaskConicalIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { PageHeader } from '@shared/components/page-parts';
import TenantShell from '@shared/components/TenantShell';
import { Alert, AlertDescription } from '@shared/components/ui/alert';

import type { SchoolSummary } from '../types/master';

/**
 * Frame for every master-data page: tenant shell, title, and the notice
 * for pages that are still mockups (`mock`, on by default until a page is
 * backed by the database). Refusals that belong to no field (a year that
 * cannot be deleted, ...) arrive as `errors.status` and show as an alert.
 */
export default function MasterPage({
    title,
    description,
    actions,
    back,
    width = 'max-w-6xl',
    mock = true,
    children,
}: {
    school: SchoolSummary;
    title: string;
    description?: string;
    actions?: ReactNode;
    back?: { href: string; label: string };
    width?: string;
    mock?: boolean;
    children: ReactNode;
}) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;

    return (
        <TenantShell width={width}>
            <Head title={title} />

            {mock && (
                <Alert className="mb-6">
                    <FlaskConicalIcon />
                    <AlertDescription>
                        Tampilan contoh — data belum tersimpan.
                    </AlertDescription>
                </Alert>
            )}

            {errors?.status !== undefined && (
                <Alert variant="destructive" className="mb-6">
                    <AlertDescription>{errors.status}</AlertDescription>
                </Alert>
            )}

            {back !== undefined && (
                <Link
                    href={back.href}
                    className="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ChevronLeftIcon className="size-4" />
                    {back.label}
                </Link>
            )}

            <PageHeader
                title={title}
                description={description}
                actions={actions}
            />

            <div className="mt-8">{children}</div>
        </TenantShell>
    );
}
