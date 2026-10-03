import type { ReactNode } from 'react';

import { useCan } from '@shared/hooks/useCan';

/** Renders its children only when the user holds `permission`. */
export default function Can({
    permission,
    children,
}: {
    permission: string;
    children: ReactNode;
}) {
    const can = useCan();

    return can(permission) ? <>{children}</> : null;
}
