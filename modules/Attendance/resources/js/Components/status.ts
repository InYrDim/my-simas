export type AttendanceStatus = 'present' | 'late' | 'sick' | 'permit' | 'absent';

/** Status word and badge variant; the word always carries the meaning. */
export const statusMeta: Record<
    AttendanceStatus,
    { label: string; short: string; variant: 'default' | 'outline' | 'secondary' | 'destructive' }
> = {
    present: { label: 'Hadir', short: 'H', variant: 'default' },
    late: { label: 'Terlambat', short: 'T', variant: 'secondary' },
    sick: { label: 'Sakit', short: 'S', variant: 'outline' },
    permit: { label: 'Izin', short: 'I', variant: 'secondary' },
    absent: { label: 'Alpa', short: 'A', variant: 'destructive' },
};

/** Statuses of a day: being late is a matter of the school gate. */
export const dailyStatuses: AttendanceStatus[] = ['present', 'late', 'sick', 'permit', 'absent'];

/** Statuses of a lesson. */
export const lessonStatuses: AttendanceStatus[] = ['present', 'sick', 'permit', 'absent'];

/** The colour of a pressed status button, beyond the default for "Hadir". */
export const pressedClass: Partial<Record<AttendanceStatus, string>> = {
    absent: 'bg-destructive hover:bg-destructive/90',
    sick: 'bg-accent hover:bg-accent/90',
    permit: 'bg-secondary hover:bg-secondary/90',
    late: 'bg-secondary hover:bg-secondary/90',
};

export interface ClassOption {
    value: string;
    label: string;
    /** A class the signed-in teacher teaches or leads. */
    mine: boolean;
}
