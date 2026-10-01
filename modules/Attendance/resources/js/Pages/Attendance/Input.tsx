import { useState } from 'react';

import { OptionSelect, Panel } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { cn } from '@shared/lib/utils';

import AttendancePage from '../../Components/AttendancePage';
import { statusMeta, statusOrder } from '../../Components/status';
import type { AttendanceStatus } from '../../Components/status';

interface Student {
    id: number;
    name: string;
    nis: string;
    status: AttendanceStatus;
}

interface InputProps {
    date: { iso: string; label: string };
    classes: { value: string; label: string }[];
    students: Student[];
}

/**
 * Input Absensi: one class, one day. Everyone starts as present, so the
 * teacher only touches the exceptions.
 */
export default function Input({ date, classes, students }: InputProps) {
    const [classId, setClassId] = useState(classes[0]?.value ?? '');
    const [marks, setMarks] = useState<Record<number, AttendanceStatus>>(
        Object.fromEntries(students.map((student) => [student.id, student.status])),
    );
    const [saved, setSaved] = useState(false);

    const counts = statusOrder.map((status) => ({
        status,
        count: students.filter((student) => marks[student.id] === status).length,
    }));

    function mark(id: number, status: AttendanceStatus) {
        setSaved(false);
        setMarks((current) => ({ ...current, [id]: status }));
    }

    return (
        <AttendancePage
            title="Input Absensi"
            description={date.label}
            width="max-w-3xl"
        >
            <div className="mb-6 max-w-xs">
                <OptionSelect
                    label="Kelas"
                    value={classId}
                    onChange={setClassId}
                    options={classes}
                />
            </div>

            <Panel>
                <ul className="flex flex-col divide-y divide-border">
                    {students.map((student) => (
                        <li
                            key={student.id}
                            className="flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium">{student.name}</p>
                                <p className="font-mono text-xs text-muted-foreground">{student.nis}</p>
                            </div>
                            <div role="group" aria-label={`Status ${student.name}`} className="flex gap-1.5">
                                {statusOrder.map((status) => {
                                    const active = marks[student.id] === status;

                                    return (
                                        <Button
                                            key={status}
                                            type="button"
                                            size="sm"
                                            variant={active ? 'default' : 'outline'}
                                            aria-pressed={active}
                                            onClick={() => mark(student.id, status)}
                                            className={cn(
                                                'min-w-16 flex-1 sm:flex-none',
                                                active && status === 'absent' && 'bg-destructive hover:bg-destructive/90',
                                                active && status === 'sick' && 'bg-accent hover:bg-accent/90',
                                                active && status === 'permit' && 'bg-secondary hover:bg-secondary/90',
                                            )}
                                        >
                                            {statusMeta[status].label}
                                        </Button>
                                    );
                                })}
                            </div>
                        </li>
                    ))}
                </ul>
            </Panel>

            <div className="sticky bottom-0 mt-6 flex flex-wrap items-center justify-between gap-3 border border-border bg-card p-4 shadow-sm">
                <div className="flex flex-wrap gap-2">
                    {counts.map(({ status, count }) => (
                        <Badge key={status} variant={statusMeta[status].variant}>
                            {statusMeta[status].label} {count}
                        </Badge>
                    ))}
                </div>
                <div className="flex items-center gap-3">
                    {saved && (
                        <span role="status" className="text-sm text-muted-foreground">
                            Contoh saja — belum tersimpan.
                        </span>
                    )}
                    <Button onClick={() => setSaved(true)}>Simpan absensi</Button>
                </div>
            </div>
        </AttendancePage>
    );
}
