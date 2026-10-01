import { Badge } from '@shared/components/ui/badge';

type Variant = 'default' | 'secondary' | 'destructive' | 'outline';

const statuses: Record<string, [Variant, string]> = {
    active: ['default', 'Aktif'],
    draft: ['outline', 'Draf'],
    archived: ['secondary', 'Arsip'],
    graduated: ['secondary', 'Lulus'],
    transferred: ['outline', 'Pindah'],
    left: ['destructive', 'Keluar'],
    maintenance: ['outline', 'Perbaikan'],
    linked: ['default', 'Punya akun'],
    unlinked: ['outline', 'Belum punya akun'],
};

/** Status word on a shared Badge; the word always carries the meaning. */
export default function StatusBadge({ status }: { status: string }) {
    const [variant, label] = statuses[status] ?? ['secondary', status];

    return <Badge variant={variant}>{label}</Badge>;
}
