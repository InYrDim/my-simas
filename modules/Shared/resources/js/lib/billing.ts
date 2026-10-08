import type {
    BillingCycleKey,
    BillingInvoice,
    BillingState,
} from '@shared/types/billing';

/** "Rp 750.000" */
export function formatRupiah(amount: number): string {
    return `Rp ${amount.toLocaleString('id-ID')}`;
}

/** "8 Okt 2026" from a `Y-m-d` date. */
export function formatDate(iso: string): string {
    return new Date(`${iso}T00:00:00`).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export const cycleLabel: Record<BillingCycleKey, string> = {
    monthly: 'bulan',
    yearly: 'tahun',
};

export const stateLabel: Record<BillingState, string> = {
    trial: 'Uji coba',
    trial_expired: 'Uji coba berakhir',
    active: 'Berlangganan',
    due: 'Segera berakhir',
    overdue: 'Menunggak',
    cancelled: 'Dihentikan',
    exempt: 'Bebas tagihan',
    none: 'Belum berlangganan',
};

/** Which badge colour a state gets: calm, to watch, or needs action. */
export const stateTone: Record<
    BillingState,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    trial: 'secondary',
    trial_expired: 'destructive',
    active: 'default',
    due: 'outline',
    overdue: 'destructive',
    cancelled: 'secondary',
    exempt: 'secondary',
    none: 'outline',
};

export const invoiceStatusLabel: Record<BillingInvoice['status'], string> = {
    unpaid: 'Belum dibayar',
    overdue: 'Terlambat',
    paid: 'Lunas',
    void: 'Dibatalkan',
};

export const invoiceKindLabel: Record<BillingInvoice['kind'], string> = {
    activation: 'Aktivasi',
    renewal: 'Perpanjangan',
    upgrade: 'Selisih naik paket',
};

/** "Hingga 300 siswa" / "Siswa tanpa batas". */
export function limitText(
    value: number | null,
    noun: string,
    unit?: string,
): string {
    if (value === null) {
        return `${noun} tanpa batas`;
    }

    return `Hingga ${value.toLocaleString('id-ID')}${unit ? ` ${unit}` : ''} ${noun}`.trim();
}
