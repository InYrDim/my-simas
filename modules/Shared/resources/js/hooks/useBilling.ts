import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

import type { SchoolBilling } from '../types/billing';

type PageProps = {
    billing?: SchoolBilling | null;
    flash?: { status?: string | null };
};

/**
 * The school's subscription for the account panels. The server sends the
 * `billing` prop only when asked, so opening a panel asks for it with a
 * partial reload: `undefined` while it loads, `null` when the user may not
 * see billing.
 *
 * `act` runs one of the billing actions (a link the server put in the
 * prop), keeps the dialog open, refreshes the prop and hands back the
 * server's message or the reason it was refused.
 */
export function useBilling(active: boolean) {
    const billing = usePage<PageProps>().props.billing;
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const reload = useCallback(() => {
        router.reload({ only: ['billing'] });
    }, []);

    useEffect(() => {
        if (active) {
            reload();
        }
    }, [active, reload]);

    const act = useCallback(
        (
            method: 'post' | 'put' | 'delete',
            url: string,
            data: Record<string, string> = {},
            onSuccess?: () => void,
        ) => {
            setBusy(true);
            setError(null);
            setNotice(null);

            router.visit(url, {
                method,
                data,
                preserveScroll: true,
                preserveState: true,
                only: ['billing', 'flash', 'errors'],
                onSuccess: (page) => {
                    setNotice((page.props as PageProps).flash?.status ?? null);
                    onSuccess?.();
                },
                onError: (errors) => {
                    setError(
                        errors.billing ??
                            Object.values(errors)[0] ??
                            'Permintaan tidak bisa diproses.',
                    );
                },
                onFinish: () => setBusy(false),
            });
        },
        [],
    );

    return { billing, busy, error, notice, act, reload };
}
