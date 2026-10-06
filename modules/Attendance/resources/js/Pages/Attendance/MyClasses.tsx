import { Link } from "@inertiajs/react";

import MyClassStudentsController from "@/actions/Modules/Attendance/App/Http/Controllers/MyClassStudentsController";
import { EmptyState, Panel } from "@shared/components/page-parts";
import { Badge } from "@shared/components/ui/badge";

import AttendancePage from "../../Components/AttendancePage";

interface MyClassesProps {
    classes: {
        id: number;
        name: string;
        subjects: string[];
        days: string[];
        lessons: number;
    }[];
}

/**
 * Kelas Saya › Kelas Aktif: the classes the teacher actually has lessons
 * for, straight from the lesson timetable and the bell slots.
 */
export default function MyClasses({ classes }: MyClassesProps) {
    return (
        <AttendancePage
            title="Kelas Aktif"
            description="Kelas yang Anda ajar pada jadwal pelajaran tahun ajaran aktif"
            width="max-w-3xl"
        >
            {classes.length === 0 ? (
                <EmptyState>
                    Belum ada kelas pada jadwal mengajar Anda. Admin sekolah
                    mengisi jadwal di Akademik › Jadwal Pelajaran.
                </EmptyState>
            ) : (
                <Panel>
                    <ul className="divide-y divide-border">
                        {classes.map((classGroup) => (
                            <li
                                key={classGroup.id}
                                className="flex flex-col gap-2 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="min-w-0">
                                    <Link
                                        href={MyClassStudentsController.url(
                                            classGroup.id,
                                        )}
                                        className="text-sm font-medium text-foreground hover:underline"
                                    >
                                        {classGroup.name}
                                    </Link>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {classGroup.subjects.join(" · ")}
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="secondary">
                                        {classGroup.lessons} jam/minggu
                                    </Badge>
                                    <span className="text-xs text-muted-foreground">
                                        {classGroup.days.join(", ")}
                                    </span>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Panel>
            )}
        </AttendancePage>
    );
}
