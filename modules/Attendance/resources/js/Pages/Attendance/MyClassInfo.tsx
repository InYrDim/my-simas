import {
    DefinitionList,
    EmptyState,
    Panel,
} from '@shared/components/page-parts';

import AttendancePage from '../../Components/AttendancePage';

interface MyClassInfoProps {
    class: {
        name: string;
        homeroom: string | null;
        studentCount: number;
    } | null;
    classmates: string[];
}

/** Kelas Saya › Info Kelas: the student's class, its homeroom teacher and classmates. */
export default function MyClassInfo({
    class: classGroup,
    classmates,
}: MyClassInfoProps) {
    return (
        <AttendancePage
            title="Info Kelas"
            description="Kelas Anda pada tahun ajaran aktif"
            width="max-w-3xl"
        >
            {classGroup === null ? (
                <EmptyState>
                    Anda belum ditempatkan di kelas. Hubungi admin sekolah.
                </EmptyState>
            ) : (
                <div className="space-y-6">
                    <Panel>
                        <DefinitionList
                            rows={[
                                ['Kelas', classGroup.name],
                                ['Wali kelas', classGroup.homeroom ?? '—'],
                                ['Jumlah siswa', classGroup.studentCount],
                            ]}
                        />
                    </Panel>

                    <Panel>
                        <h2 className="text-sm font-medium text-foreground">
                            Teman sekelas
                        </h2>
                        <ol className="mt-3 grid gap-x-8 gap-y-1 text-sm text-foreground sm:grid-cols-2">
                            {classmates.map((name, index) => (
                                <li key={`${index}-${name}`}>
                                    <span className="mr-2 font-mono text-xs text-muted-foreground">
                                        {index + 1}
                                    </span>
                                    {name}
                                </li>
                            ))}
                        </ol>
                    </Panel>
                </div>
            )}
        </AttendancePage>
    );
}
