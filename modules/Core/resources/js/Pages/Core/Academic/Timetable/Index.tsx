import { router } from '@inertiajs/react';
import { PencilIcon } from 'lucide-react';
import { useState } from 'react';

import {
    index,
    update,
} from '@/actions/Modules/Core/App/Http/Controllers/TimetableController';
import {
    DataTable,
    EmptyState,
    OptionSelect,
} from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@shared/components/ui/tabs';

import FormDialog from '../../../../Components/FormDialog';
import { SelectField } from '../../../../Components/FormField';
import MasterPage from '../../../../Components/MasterPage';
import type { SchoolSummary } from '../../../../types/master';

interface Slot {
    id: number;
    order: number;
    start: string;
    end: string;
    subjectId: number | null;
    subject: string | null;
    teacher: string | null;
}

interface Day {
    day: string;
    dayNumber: number;
    slots: Slot[];
}

interface TimetableProps {
    school: SchoolSummary;
    classes: { id: number; name: string }[];
    classId: number | null;
    /** The class's subjects that have a teacher: "Mapel · Guru". */
    subjects: { value: string; label: string }[];
    days: Day[];
}

/** Jadwal Pelajaran: one class's weekly lessons, one tab per weekday. */
export default function TimetableIndex({
    school,
    classes,
    classId,
    subjects,
    days,
}: TimetableProps) {
    const firstWithSlots = days.find((day) => day.slots.length > 0) ?? days[0];
    const [tab, setTab] = useState(String(firstWithSlots?.dayNumber ?? 1));

    return (
        <MasterPage
            school={school}
            title="Jadwal Pelajaran"
            description="Isi pelajaran tiap jam untuk satu kelas. Gurunya mengikuti Pengampu Mapel."
            width="max-w-4xl"
            mock={false}
            writePermission="core.academic.manage"
        >
            {classId === null ? (
                <EmptyState>
                    Belum ada kelas pada tahun ajaran aktif.
                </EmptyState>
            ) : (
                <>
                    <div className="mb-4 max-w-xs">
                        <OptionSelect
                            label="Kelas"
                            value={String(classId)}
                            onChange={(value) =>
                                router.get(
                                    index.url({ query: { kelas: value } }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            options={classes.map((group) => ({
                                value: String(group.id),
                                label: `Kelas ${group.name}`,
                            }))}
                        />
                    </div>

                    <Tabs value={tab} onValueChange={setTab}>
                        <TabsList>
                            {days.map((day) => (
                                <TabsTrigger
                                    key={day.dayNumber}
                                    value={String(day.dayNumber)}
                                >
                                    {day.day}
                                </TabsTrigger>
                            ))}
                        </TabsList>

                        {days.map((day) => (
                            <TabsContent
                                key={day.dayNumber}
                                value={String(day.dayNumber)}
                                className="mt-6"
                            >
                                {day.slots.length === 0 ? (
                                    <EmptyState>
                                        Belum ada jam pelajaran untuk hari{' '}
                                        {day.day}. Atur dulu di Jam Pelajaran.
                                    </EmptyState>
                                ) : (
                                    <DataTable
                                        head={[
                                            'Jam ke',
                                            'Waktu',
                                            'Pelajaran',
                                            'Guru',
                                            '',
                                        ]}
                                    >
                                        {day.slots.map((slot) => (
                                            <TableRow key={slot.id}>
                                                <TableCell className="font-medium">
                                                    {slot.order}
                                                </TableCell>
                                                <TableCell>
                                                    {slot.start}–{slot.end}
                                                </TableCell>
                                                <TableCell>
                                                    {slot.subject ?? '—'}
                                                </TableCell>
                                                <TableCell>
                                                    {slot.teacher ?? '—'}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex justify-end">
                                                        <FormDialog
                                                            route={update()}
                                                            title={`${day.day}, jam ke-${slot.order}`}
                                                            description={`${slot.start}–${slot.end}`}
                                                            trigger={
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label={`Atur jam ke-${slot.order}`}
                                                                >
                                                                    <PencilIcon />
                                                                </Button>
                                                            }
                                                        >
                                                            <input
                                                                type="hidden"
                                                                name="period_slot_id"
                                                                value={slot.id}
                                                            />
                                                            <input
                                                                type="hidden"
                                                                name="class_id"
                                                                value={classId}
                                                            />
                                                            <SelectField
                                                                label="Pelajaran"
                                                                id="subject_id"
                                                                options={
                                                                    subjects
                                                                }
                                                                defaultValue={
                                                                    slot.subjectId === null
                                                                        ? undefined
                                                                        : String(
                                                                              slot.subjectId,
                                                                          )
                                                                }
                                                                optionalLabel="Kosongkan jam ini"
                                                                hint={
                                                                    subjects.length ===
                                                                    0
                                                                        ? 'Kelas ini belum punya pengampu. Atur di Pengampu Mapel.'
                                                                        : undefined
                                                                }
                                                            />
                                                        </FormDialog>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </DataTable>
                                )}
                            </TabsContent>
                        ))}
                    </Tabs>
                </>
            )}
        </MasterPage>
    );
}
