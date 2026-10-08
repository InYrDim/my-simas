import type { ReactNode } from 'react';

import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Skeleton } from '@shared/components/ui/skeleton';

/** One label/value line of an account panel. */
export function Row({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex items-baseline justify-between gap-6 border-b border-border py-2.5 text-sm last:border-b-0">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right text-foreground">{children}</dd>
        </div>
    );
}

/** Stands in for a panel while its data is on the way. */
export function PanelSkeleton() {
    return (
        <div
            className="flex flex-col gap-4"
            aria-busy="true"
            aria-label="Memuat"
        >
            <Skeleton className="h-6 w-1/2" />
            <Skeleton className="h-4 w-1/3" />
            <Skeleton className="h-16 w-full" />
            <Skeleton className="h-16 w-full" />
        </div>
    );
}

/** What the last action answered: a refusal, or the server's message. */
export function Feedback({
    error,
    notice,
}: {
    error: string | null;
    notice: string | null;
}) {
    if (error !== null) {
        return (
            <Alert variant="destructive" role="alert">
                <AlertDescription>{error}</AlertDescription>
            </Alert>
        );
    }

    if (notice !== null) {
        return (
            <Alert role="status">
                <AlertDescription>{notice}</AlertDescription>
            </Alert>
        );
    }

    return null;
}
