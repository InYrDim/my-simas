import { Head } from '@inertiajs/react';
import { useState } from 'react';

import ProviderLayout from '../../../Components/ProviderLayout';
import {
    Chip,
    PageHeader,
    StatusChip,
    Table,
    buttonGhost,
    buttonStamp,
    inputClass,
} from '../../../Components/ui';
import { formatDate } from '../../../Components/format';
import type { ProviderUserRow } from '../../../types/console';

/**
 * Provider operator accounts. The invite form is a mock: it opens and
 * closes but sends nothing.
 */
export default function ProviderUsersIndex({
    users,
}: {
    users: ProviderUserRow[];
}) {
    const [inviting, setInviting] = useState(false);
    const [sent, setSent] = useState(false);

    return (
        <ProviderLayout>
            <Head title="Pengguna" />

            <PageHeader
                title="Pengguna"
                description="Akun operator yang bisa masuk ke console ini."
                actions={
                    <button
                        type="button"
                        className={buttonStamp}
                        onClick={() => {
                            setInviting(true);
                            setSent(false);
                        }}
                    >
                        Tambah operator
                    </button>
                }
            />

            {sent && (
                <div
                    role="status"
                    className="mt-6 rounded-lg border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-foreground"
                >
                    Contoh tampilan: undangan dikirim. Perubahan belum disimpan
                    (tahap tampilan).
                </div>
            )}

            {inviting && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        setInviting(false);
                        setSent(true);
                    }}
                    className="mt-6 grid gap-4 rounded-lg border border-border bg-card p-5 sm:grid-cols-3"
                >
                    <label className="text-sm font-medium text-foreground/80">
                        Nama
                        <input required className={`${inputClass} mt-2`} />
                    </label>
                    <label className="text-sm font-medium text-foreground/80">
                        Email
                        <input required type="email" className={`${inputClass} mt-2`} />
                    </label>
                    <label className="text-sm font-medium text-foreground/80">
                        Peran
                        <select className={`${inputClass} mt-2`} defaultValue="Support">
                            <option>Owner</option>
                            <option>Support</option>
                            <option>Finance</option>
                        </select>
                    </label>
                    <div className="flex gap-3 sm:col-span-3">
                        <button type="submit" className={buttonStamp}>
                            Kirim undangan
                        </button>
                        <button
                            type="button"
                            className={buttonGhost}
                            onClick={() => setInviting(false)}
                        >
                            Batal
                        </button>
                    </div>
                </form>
            )}

            <div className="mt-8">
                <Table head={['Operator', 'Peran', 'Status', 'Masuk terakhir']}>
                    {users.map((user) => (
                        <tr key={user.id}>
                            <td className="px-4 py-3">
                                <p className="font-medium text-foreground">{user.name}</p>
                                <p className="mt-0.5 text-xs text-muted-foreground">{user.email}</p>
                            </td>
                            <td className="px-4 py-3">
                                <Chip tone="muted">{user.role}</Chip>
                            </td>
                            <td className="px-4 py-3">
                                <StatusChip status={user.active ? 'active' : 'inactive'} />
                            </td>
                            <td className="px-4 py-3">{formatDate(user.lastLoginAt)}</td>
                        </tr>
                    ))}
                </Table>
            </div>
        </ProviderLayout>
    );
}
