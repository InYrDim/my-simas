import { ChevronLeftIcon, ChevronRightIcon, PlusIcon } from 'lucide-react';
import { useState } from 'react';

import {
    destroy,
    store,
    update,
} from '@/actions/Modules/Core/App/Http/Controllers/CalendarEventController';
import { EmptyState, Panel } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { cn } from '@shared/lib/utils';

import ConfirmAction from '../../../../Components/ConfirmAction';
import FormDialog from '../../../../Components/FormDialog';
import { InputField, SelectField } from '../../../../Components/FormField';
import { formatRange } from '../../../../Components/format';
import MasterPage from '../../../../Components/MasterPage';
import type {
    CalendarEvent,
    EventCategory,
    SchoolSummary,
} from '../../../../types/master';

const categories: Record<EventCategory, { label: string; dot: string }> = {
    holiday: { label: 'Libur', dot: 'bg-destructive' },
    exam: { label: 'Ujian', dot: 'bg-chart-1' },
    activity: { label: 'Kegiatan', dot: 'bg-primary' },
};

const monthName = new Intl.DateTimeFormat('id-ID', {
    month: 'long',
    year: 'numeric',
});
const weekdays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

function iso(year: number, month: number, day: number): string {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function eventsOn(events: CalendarEvent[], date: string): CalendarEvent[] {
    return events.filter(
        (event) => date >= event.date && date <= (event.endDate ?? event.date),
    );
}

function EventForm({ event }: { event?: CalendarEvent }) {
    return (
        <>
            <InputField label="Judul" id="title" defaultValue={event?.title} />
            <SelectField
                label="Kategori"
                id="category"
                options={Object.entries(categories).map(([value, item]) => ({
                    value,
                    label: item.label,
                }))}
                defaultValue={event?.category}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <InputField
                    label="Mulai"
                    id="start_date"
                    type="date"
                    defaultValue={event?.date}
                />
                <InputField
                    label="Selesai"
                    id="end_date"
                    type="date"
                    hint="Kosongkan untuk satu hari."
                    defaultValue={event?.endDate ?? ''}
                />
            </div>
        </>
    );
}

/** Kalender Akademik: month grid (colour per category) plus the full list. */
export default function CalendarIndex({
    school,
    events,
    today,
}: {
    school: SchoolSummary;
    events: CalendarEvent[];
    today: string;
}) {
    const [cursor, setCursor] = useState(() => {
        const [year, month] = today.split('-').map(Number);

        return { year, month: month - 1 };
    });
    const first = new Date(cursor.year, cursor.month, 1);
    const offset = (first.getDay() + 6) % 7;
    const daysInMonth = new Date(cursor.year, cursor.month + 1, 0).getDate();
    const cells = [
        ...Array.from({ length: offset }, () => null),
        ...Array.from({ length: daysInMonth }, (_, index) => index + 1),
    ];

    function shift(delta: number) {
        const next = new Date(cursor.year, cursor.month + delta, 1);
        setCursor({ year: next.getFullYear(), month: next.getMonth() });
    }

    return (
        <MasterPage
            school={school}
            title="Kalender Akademik"
            description="Hari libur, ujian, dan kegiatan sepanjang tahun ajaran."
            mock={false}
            writePermission="core.academic.manage"
            actions={
                <FormDialog
                    route={store()}
                    title="Tambah peristiwa"
                    trigger={
                        <Button>
                            <PlusIcon />
                            Tambah peristiwa
                        </Button>
                    }
                >
                    <EventForm />
                </FormDialog>
            }
        >
            <div className="grid gap-6 lg:grid-cols-5">
                <Panel className="lg:col-span-3">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-base font-semibold capitalize">
                            {monthName.format(first)}
                        </h2>
                        <div className="flex gap-1">
                            <Button
                                variant="ghost"
                                size="sm"
                                aria-label="Bulan sebelumnya"
                                onClick={() => shift(-1)}
                            >
                                <ChevronLeftIcon />
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                aria-label="Bulan berikutnya"
                                onClick={() => shift(1)}
                            >
                                <ChevronRightIcon />
                            </Button>
                        </div>
                    </div>

                    <div className="grid grid-cols-7 gap-px text-center text-xs text-muted-foreground">
                        {weekdays.map((day) => (
                            <div key={day} className="pb-2">
                                {day}
                            </div>
                        ))}
                        {cells.map((day, index) => {
                            if (day === null) {
                                return <div key={`blank-${index}`} />;
                            }

                            const date = iso(cursor.year, cursor.month, day);
                            const matches = eventsOn(events, date);

                            return (
                                <div
                                    key={day}
                                    className={cn(
                                        'flex min-h-14 flex-col items-center gap-1 border border-border/50 p-1 text-sm text-foreground',
                                        matches.length > 0 && 'bg-muted/50',
                                        date === today && 'ring-1 ring-primary',
                                    )}
                                >
                                    <span>{day}</span>
                                    <span className="flex gap-0.5">
                                        {matches.map((event) => (
                                            <span
                                                key={event.id}
                                                title={event.title}
                                                className={cn(
                                                    'size-1.5 rounded-full',
                                                    categories[event.category]
                                                        .dot,
                                                )}
                                            />
                                        ))}
                                    </span>
                                </div>
                            );
                        })}
                    </div>

                    <ul className="mt-4 flex flex-wrap gap-4 text-xs text-muted-foreground">
                        {Object.values(categories).map((item) => (
                            <li
                                key={item.label}
                                className="flex items-center gap-1.5"
                            >
                                <span
                                    className={cn(
                                        'size-2 rounded-full',
                                        item.dot,
                                    )}
                                />
                                {item.label}
                            </li>
                        ))}
                    </ul>
                </Panel>

                <Panel title="Semua peristiwa" className="lg:col-span-2">
                    {events.length === 0 ? (
                        <EmptyState>
                            Belum ada peristiwa di kalender.
                        </EmptyState>
                    ) : (
                        <ul className="flex flex-col gap-4">
                            {events.map((event) => (
                                <li
                                    key={event.id}
                                    className="flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <p className="text-sm font-medium">
                                            {event.title}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatRange(
                                                event.date,
                                                event.endDate,
                                            )}
                                        </p>
                                        <div className="mt-1 flex gap-1">
                                            <FormDialog
                                                route={update(event.id)}
                                                title={`Ubah ${event.title}`}
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                    >
                                                        Ubah
                                                    </Button>
                                                }
                                            >
                                                <EventForm event={event} />
                                            </FormDialog>
                                            <ConfirmAction
                                                route={destroy(event.id)}
                                                title={`Hapus ${event.title}?`}
                                                description="Peristiwa akan dihapus dari kalender akademik."
                                                confirmLabel="Hapus"
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                    >
                                                        Hapus
                                                    </Button>
                                                }
                                            />
                                        </div>
                                    </div>
                                    <Badge variant="outline">
                                        {categories[event.category].label}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
        </MasterPage>
    );
}
