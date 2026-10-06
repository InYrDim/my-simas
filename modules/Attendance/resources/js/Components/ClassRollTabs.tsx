import { Link } from '@inertiajs/react';

import { index as classRoll } from '@/actions/Modules/Attendance/App/Http/Controllers/ClassAttendanceController';
import { index as history } from '@/actions/Modules/Attendance/App/Http/Controllers/HistoryController';
import { Tabs, TabsList, TabsTrigger } from '@shared/components/ui/tabs';

/**
 * Absensi Kelas has two doors: fill the lesson that is running now, or
 * correct a record afterwards. One menu entry, two tabs.
 */
export default function ClassRollTabs({
    current,
}: {
    current: 'fill' | 'correct';
}) {
    return (
        <Tabs value={current} className="mb-6">
            <TabsList>
                <TabsTrigger value="fill" asChild>
                    <Link href={classRoll.url()}>Isi Absensi</Link>
                </TabsTrigger>
                <TabsTrigger value="correct" asChild>
                    <Link href={history.url()}>Koreksi</Link>
                </TabsTrigger>
            </TabsList>
        </Tabs>
    );
}
