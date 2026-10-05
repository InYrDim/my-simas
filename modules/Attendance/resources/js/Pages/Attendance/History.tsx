import { Link, router } from '@inertiajs/react';

import {
    index,
    update,
} from '@/actions/Modules/Attendance/App/Http/Controllers/HistoryController';
import { DataTable, EmptyState } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';

import AttendancePage from '../../Components/AttendancePage';
import Filter from '../../Components/Filter';
import OwnLessonSheet from '../../Components/OwnLessonSheet';
import type { RollStudent } from '../../Components/LessonRoll';
import { statusMeta } from '../../Components/status';
import type { LessonState } from '../../Components/status';

interface Summary {
    present: number;
    sick: number;
    permit: number;
    absent: number;
}

interface Lesson {
    slotId: number;
    order: number;
    startsAt: string;
    endsAt: string;
    className: string;
    subjectName: string;
    state: LessonState;
    summary: Summary | null;
}

interface HistoryProps {
    date: { iso: string; label: string; isToday: boolean };
    today: string;
    lessons: Lesson[];
    selected: Lesson | null;
    recorded: boolean;
    students: RollStudent[];
}

type RecapStatus = 'present' | 'sick' | 'permit' | 'absent';

const recapStatuses: RecapStatus[] = ['present', 'sick', 'permit', 'absent'];

/**
 * Kelas Saya › Riwayat Absensi: the teacher's scheduled lessons of one
 * day in a table, each with its saved recap. A record is corrected here
 * — at any time, not only while the lesson runs.
 */
export default function History({
    date,
    today,
    lessons,
    selected,
    recorded,
    students,
}: HistoryProps) {
    return (
        <AttendancePage
            title="Riwayat Absensi"
            description="Catatan absensi jam pelajaran Anda"
            width="max-w-3xl"
        >
            <div className="mb-6 flex flex-col gap-4 sm:flex-row">
                <Filter label="Tanggal" htmlFor="history-date">
                    <Input
                        id="history-date"
                        type="date"
                        value={date.iso}
                        max={today}
                        onChange={(event) =>
                            event.target.value !== '' &&
                            router.get(
                                index.url({
                                    query: { tanggal: event.target.value },
                                }),
                            )
                        }
                    />
                </Filter>
            </div>

            {lessons.length === 0 ? (
                <EmptyState>
                    Tidak ada jadwal mengajar pada tanggal ini.
                </EmptyState>
            ) : (
                <DataTable
                    head={['Jam', 'Kelas', 'Mata pelajaran', 'Rekap', '']}
                >
                    {lessons.map((lesson) => (
                        <TableRow key={lesson.slotId}>
                            <TableCell className="font-medium">
                                Jam ke-{lesson.order}
                                <span className="block text-xs font-normal text-muted-foreground">
                                    {lesson.startsAt}–{lesson.endsAt}
                                </span>
                            </TableCell>
                            <TableCell>{lesson.className}</TableCell>
                            <TableCell className="text-muted-foreground">
                                {lesson.subjectName}
                            </TableCell>
                            <TableCell>
                                {lesson.summary === null ? (
                                    <Badge variant="outline">
                                        Belum diabsen
                                    </Badge>
                                ) : (
                                    <div className="flex flex-wrap gap-1">
                                        {recapStatuses.map((status) => (
                                            <Badge
                                                key={status}
                                                variant={
                                                    statusMeta[status].variant
                                                }
                                            >
                                                {statusMeta[status].short}{' '}
                                                {lesson.summary?.[status]}
                                            </Badge>
                                        ))}
                                    </div>
                                )}
                            </TableCell>
                            <TableCell className="text-right">
                                <Button asChild size="sm" variant="outline">
                                    <Link
                                        href={index.url({
                                            query: {
                                                tanggal: date.iso,
                                                jam: String(lesson.slotId),
                                            },
                                        })}
                                        preserveScroll
                                    >
                                        {lesson.summary === null
                                            ? 'Isi'
                                            : 'Ubah'}
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                    ))}
                </DataTable>
            )}

            {selected !== null && (
                <section className="mt-8">
                    <div className="mb-4">
                        <h2 className="text-base font-semibold text-foreground">
                            {selected.subjectName} · Kelas {selected.className}
                        </h2>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            Jam ke-{selected.order} · {selected.startsAt}–
                            {selected.endsAt} · {date.label}
                        </p>
                    </div>
                    <OwnLessonSheet
                        key={`${date.iso}-${selected.slotId}`}
                        date={date.iso}
                        slotId={String(selected.slotId)}
                        students={students}
                        recorded={recorded}
                        editable
                        updateUrl={update.url()}
                    />
                </section>
            )}
        </AttendancePage>
    );
}
