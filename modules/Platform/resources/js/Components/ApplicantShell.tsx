import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { Alert, AlertDescription } from '@shared/components/ui/alert';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';

/**
 * Frame for the applicant pages (daftar, masuk, verifikasi, onboarding):
 * one centred card on the school theme, with the flash status on top.
 * Composed from the shared shadcn primitives; no navigation — each page
 * points at one action.
 */
export default function ApplicantShell({
    title,
    description,
    width = 'max-w-sm',
    footer,
    children,
}: {
    title: string;
    description?: string;
    width?: string;
    footer?: ReactNode;
    children: ReactNode;
}) {
    const { flash } = usePage<{ flash?: { status?: string | null } }>().props;

    return (
        <div className="flex min-h-[100dvh] items-center justify-center bg-background px-4 py-10 text-foreground">
            <Head title={title} />

            <div className={`w-full ${width}`}>
                {flash?.status && (
                    <Alert className="mb-4">
                        <AlertDescription>{flash.status}</AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-xl">{title}</CardTitle>
                        {description !== undefined && (
                            <CardDescription>{description}</CardDescription>
                        )}
                    </CardHeader>

                    <CardContent>{children}</CardContent>

                    {footer !== undefined && (
                        <CardFooter className="text-sm text-muted-foreground">
                            {footer}
                        </CardFooter>
                    )}
                </Card>
            </div>
        </div>
    );
}
