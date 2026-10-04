import { Head } from '@inertiajs/react';

import TenantShell from '@shared/components/TenantShell';

type Person =
    | {
          kind: 'student';
          name: string;
          nis: string;
          nisn: string | null;
          gender: string;
          birthDate: string | null;
          class: string | null;
          guardianName: string | null;
      }
    | {
          kind: 'teacher';
          name: string;
          nip: string | null;
          nuptk: string | null;
          employment: string;
          duty: string;
          email: string | null;
      };

interface ProfileProps {
    school: { name: string; slug: string };
    person: Person | null;
    account: { name: string; login: string | null; roles: string[] } | null;
}

type Row = [label: string, value: string | null | undefined];

function rowsFor(person: Person | null): Row[] {
    if (person?.kind === 'student') {
        return [
            ['NIS', person.nis],
            ['NISN', person.nisn],
            ['Kelas', person.class],
            [
                'Jenis kelamin',
                person.gender === 'L' ? 'Laki-laki' : 'Perempuan',
            ],
            ['Tanggal lahir', person.birthDate],
            ['Wali', person.guardianName],
        ];
    }

    if (person?.kind === 'teacher') {
        return [
            ['NIP', person.nip],
            ['NUPTK', person.nuptk],
            ['Tugas', person.duty],
            ['Status kepegawaian', person.employment],
            ['Email', person.email],
        ];
    }

    return [];
}

/** Profil Saya: the signed-in person's own record, read only. */
export default function Profile({ school, person, account }: ProfileProps) {
    const rows: Row[] = [
        ...rowsFor(person),
        ['Masuk dengan', account?.login],
        ['Peran', account?.roles.join(' · ')],
        ['Kode sekolah', school.slug],
    ];

    return (
        <TenantShell width="max-w-xl">
            <Head title="Profil Saya" />

            <h1 className="text-xl leading-7 font-semibold text-foreground">
                {person?.name ?? account?.name ?? 'Profil Saya'}
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">{school.name}</p>

            {person === null && (
                <p className="mt-6 text-sm text-muted-foreground">
                    Akun ini belum dikaitkan dengan data siswa atau guru.
                    Hubungi admin sekolah.
                </p>
            )}

            <dl className="mt-8 border-t border-border">
                {rows
                    .filter(([, value]) => value)
                    .map(([label, value]) => (
                        <div
                            key={label}
                            className="flex items-baseline justify-between gap-6 border-b border-border py-3"
                        >
                            <dt className="text-xs text-muted-foreground">
                                {label}
                            </dt>
                            <dd className="text-right text-xs text-foreground">
                                {value}
                            </dd>
                        </div>
                    ))}
            </dl>
        </TenantShell>
    );
}
