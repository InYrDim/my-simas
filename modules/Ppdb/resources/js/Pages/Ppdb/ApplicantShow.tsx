import { Link, router, useForm, usePage } from '@inertiajs/react';

import { destroy, update } from '@/actions/Modules/Ppdb/App/Http/Controllers/ApplicantController';
import { store as enroll } from '@/actions/Modules/Ppdb/App/Http/Controllers/EnrollmentController';
import { update as updateVerification } from '@/actions/Modules/Ppdb/App/Http/Controllers/VerificationController';
import { applicants } from '@/routes/ppdb';
import { DefinitionList, OptionSelect, Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Field, FieldError, FieldLabel } from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import { Textarea } from '@shared/components/ui/textarea';

import ApplicantFields, { type ApplicantData } from '../../Components/ApplicantFields';
import ConfirmAction from '../../Components/ConfirmAction';
import PpdbPage from '../../Components/PpdbPage';
import { statusOf } from '../../Components/status';

type Option = { value: string; label: string };

interface ApplicantProps {
    applicant: {
        id: number;
        number: string;
        name: string;
        gender: string;
        birthPlace: string | null;
        birthDate: string;
        nisn: string | null;
        originSchool: string;
        address: string | null;
        guardianName: string;
        guardianPhone: string;
        waveId: number;
        waveName: string | null;
        pathId: number;
        pathName: string | null;
        source: string;
        sourceLabel: string;
        registeredOn: string;
        status: string;
        verificationNote: string | null;
        score: string | null;
        decision: string;
        decisionLabel: string;
        enrolled: boolean;
        enrolledOn: string | null;
    };
    period: { id: number; name: string };
    waves: Option[];
    paths: Option[];
    statuses: Option[];
    can: { manage: boolean; cancel: boolean; enroll: boolean };
}

/** The data of one applicant, to read or correct. */
function DataPanel({ applicant, waves, paths, editable }: Pick<ApplicantProps, 'applicant' | 'waves' | 'paths'> & { editable: boolean }) {
    const form = useForm<ApplicantData>({
        wave_id: String(applicant.waveId),
        path_id: String(applicant.pathId),
        name: applicant.name,
        gender: applicant.gender,
        birth_place: applicant.birthPlace ?? '',
        birth_date: applicant.birthDate,
        nisn: applicant.nisn ?? '',
        origin_school: applicant.originSchool,
        address: applicant.address ?? '',
        guardian_name: applicant.guardianName,
        guardian_phone: applicant.guardianPhone,
    });
    const errors: Partial<Record<string, string>> = form.errors;

    if (!editable) {
        return (
            <Panel title="Data pendaftar">
                <DefinitionList
                    rows={[
                        ['Nama lengkap', applicant.name],
                        ['Jenis kelamin', applicant.gender === 'L' ? 'Laki-laki' : 'Perempuan'],
                        ['Tempat, tanggal lahir', [applicant.birthPlace, applicant.birthDate].filter(Boolean).join(', ')],
                        ['NISN', applicant.nisn ?? '—'],
                        ['Asal sekolah', applicant.originSchool],
                        ['Alamat', applicant.address ?? '—'],
                        ['Nama wali', applicant.guardianName],
                        ['Telepon wali', applicant.guardianPhone],
                        ['Gelombang', applicant.waveName ?? '—'],
                        ['Jalur', applicant.pathName ?? '—'],
                    ]}
                />
            </Panel>
        );
    }

    return (
        <Panel title="Data pendaftar">
            <form
                className="flex flex-col gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(update.url({ applicant: applicant.id }), {
                        preserveScroll: true,
                        onSuccess: () => form.setDefaults(),
                    });
                }}
            >
                <ApplicantFields
                    data={form.data}
                    errors={errors}
                    onChange={(key, value) => form.setData(key, value)}
                    waves={waves}
                    paths={paths}
                />
                <div>
                    <Button type="submit" disabled={form.processing || !form.isDirty}>
                        Simpan data
                    </Button>
                </div>
            </form>
        </Panel>
    );
}

/** The committee's check: waiting, needs correction (with a note), verified. */
function VerificationPanel({ applicant, statuses }: Pick<ApplicantProps, 'applicant' | 'statuses'>) {
    const form = useForm({
        status: applicant.status,
        verification_note: applicant.verificationNote ?? '',
    });
    const errors: Partial<Record<string, string>> = form.errors;

    return (
        <Panel title="Verifikasi">
            <form
                className="flex flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(updateVerification.url({ applicant: applicant.id }), {
                        preserveScroll: true,
                        onSuccess: () => form.setDefaults(),
                    });
                }}
            >
                <Field data-invalid={errors.status !== undefined}>
                    <FieldLabel>Status</FieldLabel>
                    <OptionSelect label="Status verifikasi" value={form.data.status} onChange={(value) => form.setData('status', value)} options={statuses} />
                    {errors.status !== undefined && <FieldError>{errors.status}</FieldError>}
                </Field>
                <Field data-invalid={errors.verification_note !== undefined}>
                    <FieldLabel htmlFor="verification-note">Catatan untuk pendaftar</FieldLabel>
                    <Textarea
                        id="verification-note"
                        value={form.data.verification_note}
                        onChange={(event) => form.setData('verification_note', event.target.value)}
                        placeholder="Wajib diisi bila statusnya Perlu perbaikan."
                        aria-invalid={errors.verification_note !== undefined}
                    />
                    {errors.verification_note !== undefined && <FieldError>{errors.verification_note}</FieldError>}
                </Field>
                <div>
                    <Button type="submit" disabled={form.processing || !form.isDirty}>
                        Simpan verifikasi
                    </Button>
                </div>
            </form>
        </Panel>
    );
}

/** Re-registration of an accepted applicant: the committee gives the NIS and the applicant becomes a student. */
function EnrollPanel({ applicant }: Pick<ApplicantProps, 'applicant'>) {
    const form = useForm({ nis: '' });
    const errors: Partial<Record<string, string>> = form.errors;
    const refusal = errors.enroll;

    return (
        <Panel title="Daftar ulang">
            <form
                className="flex flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(enroll.url({ applicant: applicant.id }), { preserveScroll: true });
                }}
            >
                <p className="text-sm text-muted-foreground">
                    Catat daftar ulang setelah {applicant.name} datang melengkapi berkas. Pendaftar menjadi siswa di Warga Sekolah (belum berkelas); NIS diberikan
                    oleh sekolah.
                </p>
                {refusal !== undefined && (
                    <Alert variant="destructive">
                        <AlertDescription>{refusal}</AlertDescription>
                    </Alert>
                )}
                <Field data-invalid={errors.nis !== undefined}>
                    <FieldLabel htmlFor="enroll-nis">NIS</FieldLabel>
                    <Input
                        id="enroll-nis"
                        value={form.data.nis}
                        onChange={(event) => form.setData('nis', event.target.value)}
                        aria-invalid={errors.nis !== undefined}
                    />
                    {errors.nis !== undefined && <FieldError>{errors.nis}</FieldError>}
                </Field>
                <div>
                    <Button type="submit" disabled={form.processing}>
                        Catat daftar ulang
                    </Button>
                </div>
            </form>
        </Panel>
    );
}

/** Pendaftar: one applicant's page — data, verification, the selection result and re-registration. */
export default function ApplicantShow({ applicant, period, waves, paths, statuses, can }: ApplicantProps) {
    const page = usePage<{ errors: Record<string, string> }>();
    const cancelError = page.props.errors.applicant;
    const status = statusOf(applicant.decision === 'pending' ? applicant.status : applicant.decision);
    const editable = can.manage && !applicant.enrolled;

    return (
        <PpdbPage
            title={applicant.name}
            description={`${applicant.number} · ${applicant.sourceLabel} · terdaftar ${applicant.registeredOn} · ${period.name}`}
            actions={
                <>
                    <Badge variant={status.variant} className="self-center">
                        {status.label}
                    </Badge>
                    <Button asChild variant="outline">
                        <Link href={applicants.url()}>Kembali ke daftar</Link>
                    </Button>
                </>
            }
            width="max-w-3xl"
        >
            <div className="flex flex-col gap-6">
                {applicant.enrolled && (
                    <Alert>
                        <AlertDescription>
                            Pendaftar ini sudah daftar ulang pada {applicant.enrolledOn} dan menjadi siswa; datanya tidak diubah dari sini.
                        </AlertDescription>
                    </Alert>
                )}
                {cancelError !== undefined && (
                    <Alert variant="destructive">
                        <AlertDescription>{cancelError}</AlertDescription>
                    </Alert>
                )}

                <DataPanel applicant={applicant} waves={waves} paths={paths} editable={editable} />

                {editable && <VerificationPanel applicant={applicant} statuses={statuses} />}

                {(applicant.score !== null || applicant.decision !== 'pending') && (
                    <Panel title="Hasil seleksi">
                        <DefinitionList
                            rows={[
                                ['Nilai seleksi', applicant.score ?? '—'],
                                ['Keputusan', applicant.decisionLabel],
                            ]}
                        />
                    </Panel>
                )}

                {can.manage && can.enroll && <EnrollPanel applicant={applicant} />}

                {can.manage && can.cancel && (
                    <Panel title="Batalkan pendaftaran">
                        <p className="mb-4 text-sm text-muted-foreground">
                            Untuk pendaftaran yang salah atau ganda. Pendaftaran dihapus dan nomornya tidak dipakai lagi. Hanya bisa selama belum ada keputusan
                            seleksi.
                        </p>
                        <ConfirmAction
                            trigger={<Button variant="destructive">Batalkan pendaftaran</Button>}
                            title={`Batalkan pendaftaran ${applicant.number}?`}
                            description={`Pendaftaran ${applicant.name} dihapus dan tidak bisa dikembalikan.`}
                            confirmLabel="Batalkan pendaftaran"
                            onConfirm={() => router.delete(destroy.url({ applicant: applicant.id }))}
                        />
                    </Panel>
                )}
            </div>
        </PpdbPage>
    );
}
