const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export function formatRupiah(amount: number): string {
    return rupiah.format(amount);
}

export function formatDate(iso: string): string {
    return new Date(`${iso}T00:00:00`).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export function daysFromToday(iso: string): number {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return Math.round(
        (new Date(`${iso}T00:00:00`).getTime() - today.getTime()) / 86_400_000,
    );
}

export function relativeDue(iso: string): string {
    const days = daysFromToday(iso);

    if (days === 0) {
        return 'hari ini';
    }

    return days > 0 ? `${days} hari lagi` : `terlambat ${Math.abs(days)} hari`;
}

export const cycleLabel = { monthly: 'Bulanan', yearly: 'Tahunan' } as const;
