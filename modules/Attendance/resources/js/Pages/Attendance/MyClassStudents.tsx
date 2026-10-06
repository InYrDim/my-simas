import { Link } from "@inertiajs/react";
import { ChevronLeftIcon } from "lucide-react";

import MyClassesController from "@/actions/Modules/Attendance/App/Http/Controllers/MyClassesController";
import { DataTable, EmptyState } from "@shared/components/page-parts";
import { Button } from "@shared/components/ui/button";
import { TableCell, TableRow } from "@shared/components/ui/table";

import AttendancePage from "../../Components/AttendancePage";

interface MyClassStudentsProps {
    class: { id: number; name: string };
    students: { id: number; name: string; nis: string }[];
}

/**
 * Kelas Saya › Kelas Aktif › one class: who sits in it, read-only.
 */
export default function MyClassStudents({
    class: classGroup,
    students,
}: MyClassStudentsProps) {
    return (
        <AttendancePage
            title={`Siswa ${classGroup.name}`}
            description={`${students.length} siswa di kelas ini`}
            width="max-w-3xl"
            actions={
                <Button variant="outline" asChild>
                    <Link href={MyClassesController.url()}>
                        <ChevronLeftIcon />
                        Kelas Aktif
                    </Link>
                </Button>
            }
        >
            {students.length === 0 ? (
                <EmptyState>Belum ada siswa di kelas ini.</EmptyState>
            ) : (
                <DataTable head={["No", "Nama", "NIS"]}>
                    {students.map((student, index) => (
                        <TableRow key={student.id}>
                            <TableCell className="w-12 text-muted-foreground">
                                {index + 1}
                            </TableCell>
                            <TableCell className="font-medium">
                                {student.name}
                            </TableCell>
                            <TableCell>{student.nis}</TableCell>
                        </TableRow>
                    ))}
                </DataTable>
            )}
        </AttendancePage>
    );
}
