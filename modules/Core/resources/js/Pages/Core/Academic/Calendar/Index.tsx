import { ChevronLeftIcon, ChevronRightIcon, PlusIcon } from 'lucide-react';
import { useState } from 'react';

import { Panel } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { cn } from '@shared/lib/utils';

import FormDialog from '../../../../Components/FormDialog';
import { InputField, SelectField } from '../../../../Components/FormField';
import { formatRange } from '../../../../Components/format';
import MasterPage from '../../../../Components/MasterPage';
import type { CalendarEvent, EventCategory, SchoolSummary } from '../../../../types/master';

const categories: Record<EventCategory, { label: string; dot: string }> = {
    holiday: { label: 'Libur', dot: 'bg-destructive' },
    exam: { label: 'Ujian', dot: 'bg-chart-1' },
    activity: { label: 'Kegiatan', dot: 'bg-primary' },
};

const monthName = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' });
const weekdays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

function iso(year: number, month: number, day: number): string {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function eventsOn(events: CalendarEvent[], date: string): CalendarEvent[] {
    return events.filter(
        (event) => date >= event.date && date <= (event.endDate ?? event.date),
    );
}

/** Kalender Akademik: month grid (colour per category) plus the full list. */
export default function CalendarIndex({
    school,
    events,
}: {
    school: SchoolSummary;
    events: CalendarEvent[];
}) {
    const [cursor, setCursor] = useState({ year: 2025, month: 11 });
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
            actions={
                <FormDialog
                    title="Tambah peristiwa"
                    trigger={
                        <Button>
                            <PlusIcon />
                            Tambah peristiwa
                        </Button>
                    }
                >
                    <InputField label="Judul" id="title" />
                    <SelectField
                        label="Kategori"
                        id="category"
                        options={Object.entries(categories).map(([value, item]) => ({
                            value,
                            label: item.label,
                        }))}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <InputField label="Mulai" id="date" type="date" />
                        <InputField label="Selesai" id="endDate" type="date" />
                    </div>
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
                            <Button variant="ghost" size="sm" aria-label="Bulan sebelumnya" onClick={() => shift(-1)}>
                                <ChevronLeftIcon />
                            </Button>
                            <Button variant="ghost" size="sm" aria-label="Bulan berikutnya" onClick={() => shift(1)}>
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

                            const matches = eventsOn(events, iso(cursor.year, cursor.month, day));

                            return (
                                <div
                                    key={day}
                                    className={cn(
                                        'flex min-h-14 flex-col items-center gap-1 border border-border/50 p-1 text-sm text-foreground',
                                        matches.length > 0 && 'bg-muted/50',
                                    )}
                                >
                                    <span>{day}</span>
                                    <span className="flex gap-0.5">
                                        {matches.map((event) => (
                                            <span
                                                key={event.id}
                                                title={event.title}
                                                className={cn('size-1.5 rounded-full', categories[event.category].dot)}
                                            />
                                        ))}
                                    </span>
                                </div>
                            );
                        })}
                    </div>

                    <ul className="mt-4 flex flex-wrap gap-4 text-xs text-muted-foreground">
                        {Object.values(categories).map((item) => (
                            <li key={item.label} className="flex items-center gap-1.5">
                                <span className={cn('size-2 rounded-full', item.dot)} />
                                {item.label}
                            </li>
                        ))}
                    </ul>
                </Panel>

                <Panel title="Semua peristiwa" className="lg:col-span-2">
                    <ul className="flex flex-col gap-4">
                        {events.map((event) => (
                            <li key={event.id} className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm font-medium">{event.title}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatRange(event.date, event.endDate)}
                                    </p>
                                </div>
                                <Badge variant="outline">{categories[event.category].label}</Badge>
                            </li>
                        ))}
                    </ul>
                </Panel>
            </div>
        </MasterPage>
    );
}
