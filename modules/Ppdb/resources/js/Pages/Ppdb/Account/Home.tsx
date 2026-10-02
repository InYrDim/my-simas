import { Link, router, usePage } from '@inertiajs/react';

import { destroy as leave } from '@/actions/Modules/Ppdb/App/Http/Controllers/Account/JoinSchoolController';
import { form as applicationForm, join } from '@/routes/ppdb/account';
import { DefinitionList, Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';

import ConfirmAction from '../../../Components/ConfirmAction';
import PortalPage from '../../../Components/PortalPage';
import { statusOf } from '../../../Components/status';

interface Application {
    number: string;
    name: string;
    pathName: string | null;
    waveName: string | null;
    registeredOn: string;
    status: string;
    statusLabel: string;
    note: string | null;
    canEdit: boolean;
    resultsPublished: boolean;
    decision: string | null;
    decisionLabel: string | null;
    enrolled: boolean;
}

type Portal =
    | { state: 'no_school' }
    | { state: 'unavailable'; school: string }
    | {
          state: 'joined';
          school: string;
          period: string;
          registration: { open: boolean; waveName: string | null; closesOn: string | null; nextOpensOn: string | null };
      }
    | { state: 'applied'; school: string; application: Application };

/** Leave the school: only offered until the form has been sent. */
function LeaveSchool({ school }: { school: string }) {
    return (
        <ConfirmAction
            trigger={<Button variant="outline">Keluar dari sekolah</Button>}
            title={`Keluar dari ${school}?`}
            description="Anda bisa bergabung ke sekolah lain dengan kode sekolahnya. Belum ada formulir yang terkirim."
            confirmLabel="Keluar dari sekolah"
            onConfirm={() => router.delete(leave.url())}
        />
    );
}

function Registration({ school, application }: { school: string; application: Application }) {
    const status = statusOf(application.status);
    const decision = application.decision === null ? null : statusOf(application.decision);

    return (
        <div className="flex flex-col gap-6">
            <Panel title={`Pendaftaran di ${school}`} actions={<Badge variant={status.variant}>{application.statusLabel}</Badge>}>
                <DefinitionList
                    rows={[
                        ['Nomor pendaftaran', <span key="n" className="font-mono">{application.number}</span>],
                        ['Nama', application.name],
                        ['Jalur', application.pathName ?? '—'],
                        ['Gelombang', application.waveName ?? '—'],
                        ['Tanggal daftar', application.registeredOn],
                    ]}
                />
            </Panel>

            {application.status === 'revision' && (
                <Alert>
                    <AlertDescription>
                        <strong>Panitia meminta perbaikan.</strong> {application.note}
                    </AlertDescription>
                </Alert>
            )}
            {application.canEdit && (
                <div>
                    <Button asChild>
                        <Link href={applicationForm.url()}>Perbaiki data</Link>
                    </Button>
                </div>
            )}

            <Panel title="Hasil seleksi">
                {application.resultsPublished && decision !== null ? (
                    <div className="flex flex-col gap-2">
                        <Badge variant={decision.variant} className="self-start">
                            {application.decisionLabel}
                        </Badge>
                        {application.enrolled && <p className="text-sm text-muted-foreground">Anda sudah melakukan daftar ulang.</p>}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">Hasil seleksi belum diumumkan. Halaman ini menampilkannya setelah sekolah mengumumkan.</p>
                )}
            </Panel>
        </div>
    );
}

/** The applicant's own page: where they stand and what to do next. */
export default function Home({ account, portal }: { account: { name: string; email: string }; portal: Portal }) {
    const errors = usePage<{ errors: Record<string, string> }>().props.errors;

    return (
        <PortalPage title={`Halo, ${account.name}`} description={account.email} account={account} width="max-w-2xl">
            {errors.school !== undefined && (
                <Alert variant="destructive">
                    <AlertDescription>{errors.school}</AlertDescription>
                </Alert>
            )}

            {portal.state === 'no_school' && (
                <Panel title="Gabung ke sekolah">
                    <p className="mb-4 text-sm text-muted-foreground">
                        Untuk mendaftar, gabung ke sekolah tujuan dengan kode sekolah yang Anda terima dari sekolah. Satu akun hanya bisa mendaftar di satu
                        sekolah.
                    </p>
                    <Button asChild>
                        <Link href={join.url()}>Masukkan kode sekolah</Link>
                    </Button>
                </Panel>
            )}

            {portal.state === 'unavailable' && (
                <Panel title={portal.school}>
                    <p className="mb-4 text-sm text-muted-foreground">
                        PPDB {portal.school} sedang tidak dibuka. Anda bisa menunggu sekolah membukanya, atau keluar dan bergabung ke sekolah lain.
                    </p>
                    <LeaveSchool school={portal.school} />
                </Panel>
            )}

            {portal.state === 'joined' && (
                <Panel title={portal.school} actions={<Badge variant="secondary">{portal.period}</Badge>}>
                    {portal.registration.open ? (
                        <p className="mb-4 text-sm text-muted-foreground">
                            Pendaftaran {portal.registration.waveName} dibuka sampai {portal.registration.closesOn}. Isi formulir pendaftaran untuk
                            mendaftar.
                        </p>
                    ) : (
                        <p className="mb-4 text-sm text-muted-foreground">
                            Pendaftaran belum dibuka.
                            {portal.registration.nextOpensOn !== null && ` Gelombang berikutnya dibuka ${portal.registration.nextOpensOn}.`}
                        </p>
                    )}
                    <div className="flex flex-wrap gap-3">
                        {portal.registration.open && (
                            <Button asChild>
                                <Link href={applicationForm.url()}>Isi formulir pendaftaran</Link>
                            </Button>
                        )}
                        <LeaveSchool school={portal.school} />
                    </div>
                </Panel>
            )}

            {portal.state === 'applied' && <Registration school={portal.school} application={portal.application} />}
        </PortalPage>
    );
}
