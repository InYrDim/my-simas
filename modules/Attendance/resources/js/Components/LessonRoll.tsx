import type { ReactNode } from 'react';

import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Panel } from '@shared/components/page-parts';
import { cn } from '@shared/lib/utils';

import { lessonStatuses, pressedClass, statusMeta } from './status';
import type { AttendanceStatus } from './status';

export interface RollStudent {
    id: number;
    name: string;
    nis: string;
    status: AttendanceStatus | null;
    scanned: boolean;
    daily: AttendanceStatus | null;
}

/**
 * The roll of one lesson: every student with their status. Read-only
 * until the caller says the lesson may be changed; the save bar only
 * appears then.
 */
export default function LessonRoll({
    students,
    marks,
    onMark,
    onFillPresent,
    onSave,
    recorded,
    editable,
    processing,
    error,
    lockedNote,
    header,
}: {
    students: RollStudent[];
    marks: Record<number, AttendanceStatus | null>;
    onMark: (id: number, status: AttendanceStatus) => void;
    onFillPresent: () => void;
    onSave: () => void;
    recorded: boolean;
    editable: boolean;
    processing: boolean;
    error?: string | undefined;
    /** Why the roll cannot be changed here, shown when read-only. */
    lockedNote?: string;
    /** Page-specific controls (a subject picker, a lesson title). */
    header?: ReactNode;
}) {
    const unmarked = students.filter(
        (student) => marks[student.id] === null,
    ).length;
    const summary = lessonStatuses.map((status) => ({
        status,
        count: students.filter((student) => marks[student.id] === status)
            .length,
    }));

    return (
        <>
            {error !== undefined && (
                <Alert variant="destructive" className="mb-6">
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            {!editable && lockedNote !== undefined && (
                <Alert className="mb-6">
                    <AlertDescription>{lockedNote}</AlertDescription>
                </Alert>
            )}

            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div className="flex flex-wrap items-end gap-4">{header}</div>
                {editable && (
                    <Button
                        type="button"
                        variant="outline"
                        disabled={unmarked === 0}
                        onClick={onFillPresent}
                    >
                        Tandai sisanya hadir
                    </Button>
                )}
            </div>

            <Panel>
                <ul className="flex flex-col divide-y divide-border">
                    {students.map((student) => (
                        <li
                            key={student.id}
                            className="flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium">
                                    {student.name}
                                </p>
                                <p className="font-mono text-xs text-muted-foreground">
                                    {student.nis}
                                    {student.scanned && ' · dipindai'}
                                    {student.daily !== null &&
                                        ` · hari ini ${statusMeta[student.daily].label.toLowerCase()}`}
                                </p>
                            </div>
                            <div
                                role="group"
                                aria-label={`Status ${student.name}`}
                                className="flex flex-wrap gap-1.5"
                            >
                                {lessonStatuses.map((status) => {
                                    const active = marks[student.id] === status;

                                    return (
                                        <Button
                                            key={status}
                                            type="button"
                                            size="sm"
                                            disabled={!editable}
                                            variant={
                                                active ? 'default' : 'outline'
                                            }
                                            aria-pressed={active}
                                            onClick={() =>
                                                onMark(student.id, status)
                                            }
                                            className={cn(
                                                'min-w-16 flex-1 sm:flex-none',
                                                active && pressedClass[status],
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

            <div
                className={cn(
                    'mt-6 flex flex-wrap items-center justify-between gap-3',
                    editable &&
                        'sticky bottom-0 border border-border bg-card p-4 shadow-sm',
                )}
            >
                <div className="flex flex-wrap gap-2">
                    {summary.map((item) => (
                        <Badge
                            key={item.status}
                            variant={statusMeta[item.status].variant}
                        >
                            {statusMeta[item.status].label} {item.count}
                        </Badge>
                    ))}
                    {unmarked > 0 && (
                        <Badge variant="outline">
                            Belum ditandai {unmarked}
                        </Badge>
                    )}
                </div>
                {editable && (
                    <div className="flex items-center gap-3">
                        {recorded && (
                            <span className="text-sm text-muted-foreground">
                                Sudah pernah disimpan
                            </span>
                        )}
                        <Button
                            disabled={
                                processing || unmarked === students.length
                            }
                            onClick={onSave}
                        >
                            Simpan absensi
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}
