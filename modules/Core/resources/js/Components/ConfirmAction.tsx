import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@shared/components/ui/alert-dialog';

import type { FormRoute } from './MasterForm';

/**
 * A button that asks first, then sends the request (delete, activate, ...).
 * Refusals from the server come back as an alert on the page.
 */
export default function ConfirmAction({
    trigger,
    title,
    description,
    confirmLabel,
    route,
}: {
    trigger: ReactNode;
    title: string;
    description: string;
    confirmLabel: string;
    route: FormRoute;
}) {
    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>{trigger}</AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>{description}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction
                        onClick={() =>
                            router.visit(route.url, {
                                method: route.method,
                                preserveScroll: true,
                            })
                        }
                    >
                        {confirmLabel}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
