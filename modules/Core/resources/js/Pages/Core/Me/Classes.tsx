import { Head } from '@inertiajs/react';

import TenantShell from '@shared/components/TenantShell';

interface ClassesProps {
    school: { name: string; slug: string };
    classes: {
        id: number;
        name: string;
        homeroom: boolean;
        subjects: string[];
    }[];
}

/** Kelas Saya: the classes of the active year this teacher looks after. */
export default function Classes({ school, classes }: ClassesProps) {
    return (
        <TenantShell width="max-w-xl">
            <Head title="Kelas Saya" />

            <h1 className="text-xl leading-7 font-semibold text-foreground">
                Kelas Saya
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">
                Tahun ajaran aktif · {school.name}
            </p>

            {classes.length === 0 ? (
                <p className="mt-8 text-sm text-muted-foreground">
                    Belum ada kelas yang Anda walikan atau ampu pada tahun ajaran
                    aktif. Admin sekolah mengaturnya di Akademik.
                </p>
            ) : (
                <ul className="mt-8 border-t border-border">
                    {classes.map((classGroup) => (
                        <li
                            key={classGroup.id}
                            className="flex items-baseline justify-between gap-6 border-b border-border py-3"
                        >
                            <span className="text-sm text-foreground">
                                {classGroup.name}
                                {classGroup.homeroom && (
                                    <span className="ml-2 text-xs text-muted-foreground">
                                        wali kelas
                                    </span>
                                )}
                            </span>
                            <span className="text-right text-xs text-muted-foreground">
                                {classGroup.subjects.join(' · ')}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </TenantShell>
    );
}
