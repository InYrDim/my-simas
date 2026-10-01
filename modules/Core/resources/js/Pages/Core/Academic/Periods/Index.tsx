import { CopyIcon } from 'lucide-react';

import { DataTable } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@shared/components/ui/tabs';

import MasterPage from '../../../../Components/MasterPage';
import type { PeriodDay, SchoolSummary } from '../../../../types/master';

/** Jam Pelajaran: the bell schedule template, one tab per weekday. */
export default function PeriodsIndex({
    school,
    days,
}: {
    school: SchoolSummary;
    days: PeriodDay[];
}) {
    return (
        <MasterPage
            school={school}
            title="Jam Pelajaran"
            description="Template jam per hari: pelajaran, istirahat, dan kegiatan rutin."
            width="max-w-4xl"
            actions={
                <Button variant="outline">
                    <CopyIcon />
                    Salin ke hari lain
                </Button>
            }
        >
            <Tabs defaultValue={days[0]?.day}>
                <TabsList>
                    {days.map((day) => (
                        <TabsTrigger key={day.day} value={day.day}>
                            {day.day}
                        </TabsTrigger>
                    ))}
                </TabsList>

                {days.map((day) => (
                    <TabsContent key={day.day} value={day.day} className="mt-6">
                        <DataTable head={['Jam ke', 'Mulai', 'Selesai', 'Jenis']}>
                            {day.slots.map((slot) => (
                                <TableRow key={`${slot.start}-${slot.type}`}>
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
                                </TableRow>
                            ))}
                        </DataTable>
                    </TabsContent>
                ))}
            </Tabs>
        </MasterPage>
    );
}
