export type AttendanceStatus = 'present' | 'sick' | 'permit' | 'absent';

/** Status word and badge variant; the word always carries the meaning. */
export const statusMeta: Record<
    AttendanceStatus,
    { label: string; short: string; variant: 'default' | 'outline' | 'secondary' | 'destructive' }
> = {
    present: { label: 'Hadir', short: 'H', variant: 'default' },
    sick: { label: 'Sakit', short: 'S', variant: 'outline' },
    permit: { label: 'Izin', short: 'I', variant: 'secondary' },
    absent: { label: 'Alpa', short: 'A', variant: 'destructive' },
};

export const statusOrder: AttendanceStatus[] = ['present', 'sick', 'permit', 'absent'];
