import { Link } from '@inertiajs/react';

import {
    linkTeacher,
    resetTeacher,
    storeForTeacher,
} from '@/actions/Modules/Core/App/Http/Controllers/AccountController';
import { show as classShow } from '@/actions/Modules/Core/App/Http/Controllers/ClassGroupController';
import {
    destroy,
    index as teachersIndex,
    update,
} from '@/actions/Modules/Core/App/Http/Controllers/TeacherController';
import { index as usersIndex } from '@/actions/Modules/Identity/App/Http/Controllers/UsersManagementController';
import {
    DataTable,
    DefinitionList,
    EmptyState,
    Panel,
} from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import AccountPanel from '../../../../Components/AccountPanel';
import ConfirmAction from '../../../../Components/ConfirmAction';
import FormDialog from '../../../../Components/FormDialog';
import { SelectField } from '../../../../Components/FormField';
import MasterPage from '../../../../Components/MasterPage';
import TeacherForm from '../../../../Components/TeacherForm';
import type {
    AccountAbilities,
    Assignment,
    ClassGroup,
    LinkedAccount,
    SchoolSummary,
    Teacher,
} from '../../../../types/master';

/**
 * One teacher: profile, subjects taught, homeroom duty, and the optional
 * login account (a plain user_id link — Core never reads Identity's model).
 */
export default function TeachersShow({
    school,
    teacher,
    login,
    assignments,
    homeroomOf,
}: {
    school: SchoolSummary;
    teacher: Teacher;
    login: { account: LinkedAccount | null; can: AccountAbilities };
    assignments: Assignment[];
    homeroomOf: ClassGroup[];
}) {
    return (
        <MasterPage
            school={school}
            title={teacher.name}
            description={`${teacher.duty} · ${teacher.employment}`}
            back={{ href: teachersIndex.url(), label: 'Semua guru' }}
            mock={false}
            actions={
                <>
                    <ConfirmAction
                        route={destroy(teacher.id)}
                        title={`Hapus ${teacher.name}?`}
                        description="Guru yang masih menjadi wali kelas atau pembina tidak bisa dihapus."
                        confirmLabel="Hapus"
                        trigger={<Button variant="outline">Hapus</Button>}
                    />
                    <FormDialog
                        route={update(teacher.id)}
                        title={`Ubah ${teacher.name}`}
                        trigger={<Button variant="outline">Ubah data</Button>}
                    >
                        <TeacherForm teacher={teacher} />
                    </FormDialog>
                </>
            }
        >
            <div className="grid gap-6 lg:grid-cols-3">
                <div className="flex flex-col gap-6">
                    <Panel title="Profil">
                        <DefinitionList
                            rows={[
                                ['NIP', teacher.nip ?? '—'],
                                ['NUPTK', teacher.nuptk ?? '—'],
                                ['Status', teacher.employment],
                                ['Tugas', teacher.duty],
                                ['Email', teacher.email ?? '—'],
                            ]}
                        />
                    </Panel>

                    <AccountPanel
                        account={login.account}
                        hint="Guru ber-NIP masuk dengan NIP dan kata sandi sementara yang wajib diganti. Guru tanpa NIP diundang lewat email di halaman Pengguna, lalu ditautkan di sini."
                    >
                        {login.account === null && login.can.create && (
                            <>
                                {teacher.nip !== null && (
                                    <FormDialog
                                        route={storeForTeacher(teacher.id)}
                                        title={`Buat akun untuk ${teacher.name}`}
                                        description={`Nama pengguna ${teacher.nip}. Kata sandi sementara tampil sekali setelah akun dibuat.`}
                                        submitLabel="Buat akun"
                                        trigger={
                                            <Button variant="outline">
                                                Buat akun
                                            </Button>
                                        }
                                    >
                                        <SelectField
                                            label="Peran"
                                            id="role"
                                            options={[
                                                {
                                                    value: 'guru',
                                                    label: 'Guru',
                                                },
                                                {
                                                    value: 'staf-tu',
                                                    label: 'Staf/TU',
                                                },
                                            ]}
                                        />
                                    </FormDialog>
                                )}
                                {teacher.email !== null && (
                                    <ConfirmAction
                                        route={linkTeacher(teacher.id)}
                                        title={`Tautkan ${teacher.name} ke akun yang ada?`}
                                        description={`Akun dengan email ${teacher.email} akan menjadi akun guru ini. Akunnya sendiri tidak berubah.`}
                                        confirmLabel="Tautkan"
                                        trigger={
                                            <Button variant="outline">
                                                Tautkan akun yang ada
                                            </Button>
                                        }
                                    />
                                )}
                            </>
                        )}
                        {login.account !== null &&
                            login.account.username !== null &&
                            login.can.reset && (
                                <ConfirmAction
                                    route={resetTeacher(teacher.id)}
                                    title={`Reset kata sandi ${teacher.name}?`}
                                    description="Kata sandi baru dibuat acak, tampil sekali, dan wajib diganti saat login berikutnya."
                                    confirmLabel="Reset kata sandi"
                                    trigger={
                                        <Button variant="outline">
                                            Reset kata sandi
                                        </Button>
                                    }
                                />
                            )}
                        {login.account !== null && (
                            <Button asChild variant="outline">
                                <Link href={usersIndex.url()}>
                                    Kelola di Pengguna
                                </Link>
                            </Button>
                        )}
                    </AccountPanel>
                </div>

                <div className="flex flex-col gap-6 lg:col-span-2">
                    <Panel title={school.homeroomLabel}>
                        {homeroomOf.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Tidak menjadi{' '}
                                {school.homeroomLabel.toLowerCase()}.
                            </p>
                        ) : (
                            <ul className="flex flex-wrap gap-2">
                                {homeroomOf.map((group) => (
                                    <li key={group.id}>
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                href={classShow.url(group.id)}
                                            >
                                                {group.name}
                                            </Link>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel title="Mengampu">
                        {assignments.length === 0 ? (
                            <EmptyState>
                                Belum mengampu mata pelajaran.
                            </EmptyState>
                        ) : (
                            <DataTable
                                head={['Mata pelajaran', 'Kelas', 'JP/minggu']}
                            >
                                {assignments.map((row) => (
                                    <TableRow
                                        key={`${row.class}-${row.subject}`}
                                    >
                                        <TableCell>{row.subject}</TableCell>
                                        <TableCell>{row.class}</TableCell>
                                        <TableCell>{row.hours}</TableCell>
                                    </TableRow>
                                ))}
                            </DataTable>
                        )}
                    </Panel>
                </div>
            </div>
        </MasterPage>
    );
}
