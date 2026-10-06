import { EmptyState, Panel } from '@shared/components/page-parts';

import AttendancePage from '../../Components/AttendancePage';

interface MyClassSubjectsProps {
    className: string | null;
    subjects: { subject: string; teacher: string }[];
}

/** Kelas Saya › Mata Pelajaran & Guru: what the student's class learns and who teaches it. */
export default function MyClassSubjects({
    className,
    subjects,
}: MyClassSubjectsProps) {
    return (
        <AttendancePage
            title="Mata Pelajaran & Guru"
            description={className ? `Kelas ${className}` : undefined}
            width="max-w-3xl"
        >
            {subjects.length === 0 ? (
                <EmptyState>
                    Belum ada pengampu mata pelajaran untuk kelas Anda. Admin
                    sekolah mengaturnya di Akademik › Pengampu Mapel.
                </EmptyState>
            ) : (
                <Panel>
                    <ul className="divide-y divide-border">
                        {subjects.map((row) => (
                            <li
                                key={`${row.subject}-${row.teacher}`}
                                className="flex items-baseline justify-between gap-6 py-3 first:pt-0 last:pb-0"
                            >
                                <span className="text-sm font-medium text-foreground">
                                    {row.subject}
                                </span>
                                <span className="text-right text-sm text-muted-foreground">
                                    {row.teacher}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Panel>
            )}
        </AttendancePage>
    );
}
