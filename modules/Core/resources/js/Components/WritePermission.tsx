import { createContext, useContext } from 'react';

import { useCan } from '@shared/hooks/useCan';

/**
 * The permission that lets the user change what a master page shows.
 * MasterPage provides it; FormDialog and ConfirmAction hide themselves
 * without it, so a role that may only view never sees a button the
 * server would refuse. Outside a MasterPage nothing is hidden.
 */
export const WritePermissionContext = createContext<string | null>(null);

export const masterManage = 'core.master.manage';

export function useCanWrite(): boolean {
    const permission = useContext(WritePermissionContext);
    const can = useCan();

    return permission === null || can(permission);
}
