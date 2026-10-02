type Variant = 'default' | 'outline' | 'secondary' | 'destructive';

/** Applicant and decision statuses: the word carries the meaning. */
export const statusMeta: Record<string, { label: string; variant: Variant }> = {
    submitted: { label: 'Menunggu verifikasi', variant: 'outline' },
    verified: { label: 'Terverifikasi', variant: 'secondary' },
    revision: { label: 'Perlu perbaikan', variant: 'outline' },
    accepted: { label: 'Diterima', variant: 'default' },
    waitlist: { label: 'Cadangan', variant: 'secondary' },
    pending: { label: 'Belum diputuskan', variant: 'outline' },
    rejected: { label: 'Tidak diterima', variant: 'destructive' },
    draft: { label: 'Konsep', variant: 'outline' },
    active: { label: 'Berjalan', variant: 'default' },
    open: { label: 'Dibuka', variant: 'default' },
    closed: { label: 'Ditutup', variant: 'secondary' },
    upcoming: { label: 'Akan datang', variant: 'outline' },
};

export function statusOf(status: string): { label: string; variant: Variant } {
    return statusMeta[status] ?? { label: status, variant: 'secondary' };
}
