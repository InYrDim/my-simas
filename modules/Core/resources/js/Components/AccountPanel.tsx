import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { DefinitionList, Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription, AlertTitle } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';

import type { LinkedAccount } from '../types/master';
import StatusBadge from './StatusBadge';

/**
 * "Akun login" on a student's or a teacher's page: whether the person has
 * an account, how it signs in, and the actions the page passes in. A
 * password the server just generated is shown here once.
 */
export default function AccountPanel({
    account,
    hint,
    children,
}: {
    account: LinkedAccount | null;
    /** Shown while there is no account: how one is made. */
    hint: string;
    children?: ReactNode;
}) {
    const { flash } = usePage<{ flash?: { password?: string | null } }>().props;

    return (
        <Panel title="Akun login">
            <div className="flex flex-col gap-4">
                {flash?.password && (
                    <Alert>
                        <AlertTitle>Kata sandi sementara</AlertTitle>
                        <AlertDescription>
                            <span className="font-mono text-base text-foreground">
                                {flash.password}
                            </span>
                            <span className="block">
                                Catat sekarang: kata sandi ini hanya tampil
                                sekali dan wajib diganti saat login pertama.
                            </span>
                        </AlertDescription>
                    </Alert>
                )}

                {account === null ? (
                    <>
                        <StatusBadge status="unlinked" />
                        <p className="text-sm text-muted-foreground">{hint}</p>
                    </>
                ) : (
                    <DefinitionList
                        rows={[
                            [
                                'Status',
                                account.active ? (
                                    <Badge key="s">Aktif</Badge>
                                ) : (
                                    <Badge key="s" variant="destructive">
                                        Nonaktif
                                    </Badge>
                                ),
                            ],
                            ['Nama pengguna', account.username ?? '—'],
                            ['Email', account.email ?? '—'],
                            [
                                'Kata sandi',
                                account.mustChangePassword
                                    ? 'Masih kata sandi awal'
                                    : 'Sudah diganti pemiliknya',
                            ],
                        ]}
                    />
                )}

                {children}
            </div>
        </Panel>
    );
}
