import { CopyIcon, PlusIcon } from 'lucide-react';
import { useState } from 'react';

import {
    copy,
    destroy,
    store,
    update,
} from '@/actions/Modules/Core/App/Http/Controllers/PeriodSlotController';
import { DataTable, EmptyState } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import { Field, FieldLabel } from '@shared/components/ui/field';
import { TableCell, TableRow } from '@shared/components/ui/table';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@shared/components/ui/tabs';

import ConfirmAction from '../../../../Components/ConfirmAction';
import FormDialog from '../../../../Components/FormDialog';
import { InputField, SelectField } from '../../../../Components/FormField';
import MasterPage from '../../../../Components/MasterPage';
import type { PeriodDay, PeriodSlot, SchoolSummary } from '../../../../types/master';

function SlotForm({
    day,
    types,
    slot,
}: {
    day: PeriodDay;
    types: string[];
    slot?: PeriodSlot;
}) {
    return (
        <>
            <input type="hidden" name="day" value={day.dayNumber} />
            <div className="grid gap-4 sm:grid-cols-2">
                <InputField
                    label="Mulai"
                    id="start_time"
                    type="time"
                    defaultValue={slot?.start}
                />
                <InputField
                    label="Selesai"
                    id="end_time"
                    type="time"
                    defaultValue={slot?.end}
                />
            </div>
            <SelectField
                label="Jenis"
                id="type"
                options={types}
                defaultValue={slot?.type}
            />
        </>
    );
}

/** Jam Pelajaran: the bell schedule template, one tab per weekday. */
export default function PeriodsIndex({
    school,
    days,
    types,
}: {
    school: SchoolSummary;
    days: PeriodDay[];
    types: string[];
}) {
    const [tab, setTab] = useState(String(days[0]?.dayNumber ?? 1));
    const current = days.find((day) => String(day.dayNumber) === tab) ?? days[0];

    return (
        <MasterPage
            school={school}
            title="Jam Pelajaran"
            description="Template jam per hari: pelajaran, istirahat, dan kegiatan rutin."
            width="max-w-4xl"
            mock={false}
            actions={
                <FormDialog
                    route={copy()}
                    title={`Salin jam ${current.day}`}
                    description="Jam di hari tujuan akan diganti dengan salinan jam hari ini."
                    submitLabel="Salin"
                    trigger={
                        <Button variant="outline">
                            <CopyIcon />
                            Salin ke hari lain
                        </Button>
                    }
                >
                    <input type="hidden" name="from_day" value={current.dayNumber} />
                    {days
                        .filter((day) => day.dayNumber !== current.dayNumber)
                        .map((day) => (
                            <Field key={day.dayNumber} orientation="horizontal">
                                <Checkbox
                                    id={`copy-day-${day.dayNumber}`}
                                    name="days[]"
                                    value={String(day.dayNumber)}
                                />
                                <FieldLabel htmlFor={`copy-day-${day.dayNumber}`}>
                                    {day.day}
                                </FieldLabel>
                            </Field>
                        ))}
                </FormDialog>
            }
        >
            <Tabs value={tab} onValueChange={setTab}>
                <TabsList>
                    {days.map((day) => (
                        <TabsTrigger key={day.dayNumber} value={String(day.dayNumber)}>
                            {day.day}
                        </TabsTrigger>
                    ))}
                </TabsList>

                {days.map((day) => (
                    <TabsContent key={day.dayNumber} value={String(day.dayNumber)} className="mt-6">
                        <div className="mb-4 flex justify-end">
                            <FormDialog
                                route={store()}
                                title={`Tambah jam ${day.day}`}
                                trigger={
                                    <Button>
                                        <PlusIcon />
                                        Tambah jam
                                    </Button>
                                }
                            >
                                <SlotForm day={day} types={types} />
                            </FormDialog>
                        </div>

                        {day.slots.length === 0 ? (
                            <EmptyState>Belum ada jam untuk hari {day.day}.</EmptyState>
                        ) : (
                            <DataTable head={['Jam ke', 'Mulai', 'Selesai', 'Jenis', '']}>
                                {day.slots.map((slot) => (
                                    <TableRow key={slot.id}>
                                        <TableCell className="font-medium">
                                            {slot.order ?? '—'}
                                        </TableCell>
                                        <TableCell>{slot.start}</TableCell>
                                        <TableCell>{slot.end}</TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    slot.type === 'Pelajaran' ? 'default' : 'secondary'
                                                }
                                            >
                                                {slot.type}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                <FormDialog
                                                    route={update(slot.id)}
                                                    title={`Ubah jam ${slot.start}`}
                                                    trigger={
                                                        <Button variant="ghost" size="sm">
                                                            Ubah
                                                        </Button>
                                                    }
                                                >
                                                    <SlotForm day={day} types={types} slot={slot} />
                                                </FormDialog>
                                                <ConfirmAction
                                                    route={destroy(slot.id)}
                                                    title={`Hapus jam ${slot.start}–${slot.end}?`}
                                                    description="Jam ini akan dihapus dari template hari tersebut."
                                                    confirmLabel="Hapus"
                                                    trigger={
                                                        <Button variant="ghost" size="sm">
                                                            Hapus
                                                        </Button>
                                                    }
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </DataTable>
                        )}
                    </TabsContent>
                ))}
            </Tabs>
        </MasterPage>
    );
}
