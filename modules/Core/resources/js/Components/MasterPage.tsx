import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeftIcon, FlaskConicalIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { OptionSelect, PageHeader } from '@shared/components/page-parts';
import TenantShell from '@shared/components/TenantShell';
import { Alert, AlertDescription } from '@shared/components/ui/alert';

import type { SchoolSummary } from '../types/master';

/**
 * Frame for every master-data page: tenant shell, title, and — while the
 * pages are mockups — a notice with a jenjang switcher so each school
 * level can be previewed. The switcher and notice go away with the mock.
 */
export default function MasterPage({
    school,
    title,
    description,
    actions,
    back,
    width = 'max-w-6xl',
    children,
}: {
    school: SchoolSummary;
    title: string;
    description?: string;
    actions?: ReactNode;
    back?: { href: string; label: string };
    width?: string;
    children: ReactNode;
}) {
    const { url } = usePage();
    const path = url.split('?')[0];

    return (
        <TenantShell width={width}>
            <Head title={title} />

            <Alert className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <FlaskConicalIcon />
                <AlertDescription className="flex-1">
                    Tampilan contoh — data belum tersimpan.
                </AlertDescription>
                <div className="w-44">
                    <OptionSelect
                        label="Pratinjau jenjang"
                        value={school.level}
                        options={school.levelOptions}
                        onChange={(jenjang) =>
                            router.get(
                                path,
                                { jenjang },
                                { preserveState: true, replace: true },
                            )
                        }
                    />
                </div>
            </Alert>

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
