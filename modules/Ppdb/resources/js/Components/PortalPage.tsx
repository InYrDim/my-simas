import { Head, router, usePage } from '@inertiajs/react';
import { GraduationCapIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { destroy as signOut } from '@/actions/Modules/Ppdb/App/Http/Controllers/Account/SessionController';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Button } from '@shared/components/ui/button';
import { cn } from '@shared/lib/utils';

/**
 * Frame for the applicant's own pages (daftar, masuk, verifikasi, halaman
 * akun): one column that works on a phone, the brand on top, the account's
 * name and a sign-out button once someone is signed in. There is no school
 * navigation — an applicant is not a school user.
 */
export default function PortalPage({
    title,
    description,
    account,
    footer,
    width = 'max-w-md',
    children,
}: {
    title: string;
    description?: string;
    account?: { name: string };
    footer?: ReactNode;
    width?: string;
    children: ReactNode;
}) {
    const { flash } = usePage<{ flash?: { status?: string | null } }>().props;

    return (
        <div className="min-h-[100dvh] bg-background px-4 py-6 text-foreground sm:px-8 sm:py-10">
            <Head title={title} />

            <div className={cn('mx-auto flex w-full flex-col gap-8', width)}>
                <header className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-2 font-semibold">
                        <span className="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <GraduationCapIcon className="size-4" />
                        </span>
                        PPDB SIMAS
                    </div>
                    {account !== undefined && (
                        <div className="flex items-center gap-3 text-sm">
                            <span className="hidden text-muted-foreground sm:inline">{account.name}</span>
                            <Button variant="outline" size="sm" onClick={() => router.post(signOut.url())}>
                                Keluar
                            </Button>
                        </div>
                    )}
                </header>

                <main className="flex flex-col gap-6">
                    {flash?.status && (
                        <Alert>
                            <AlertDescription>{flash.status}</AlertDescription>
                        </Alert>
                    )}

                    <div className="flex flex-col gap-1.5">
                        <h1 className="text-2xl font-semibold tracking-tight text-balance">{title}</h1>
                        {description !== undefined && <p className="text-sm text-muted-foreground">{description}</p>}
                    </div>

                    {children}

                    {footer !== undefined && <div className="border-t pt-4 text-sm text-muted-foreground">{footer}</div>}
                </main>
            </div>
        </div>
    );
}
