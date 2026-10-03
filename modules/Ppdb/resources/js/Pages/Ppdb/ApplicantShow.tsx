import { Link, router, useForm, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import {
    destroy,
    update,
} from '@/actions/Modules/Ppdb/App/Http/Controllers/ApplicantController';
import { store as enroll } from '@/actions/Modules/Ppdb/App/Http/Controllers/EnrollmentController';
import { update as updateVerification } from '@/actions/Modules/Ppdb/App/Http/Controllers/VerificationController';
import { applicants } from '@/routes/ppdb';
import {
    DefinitionList,
    OptionSelect,
    Panel,
} from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Field, FieldError, FieldLabel } from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import { Textarea } from '@shared/components/ui/textarea';

import ConfirmAction from '../../Components/ConfirmAction';
import FormRenderer, {
    formBinding,
    initialAnswers,
    type ApplicantData,
    type FormFieldDef,
    type StoredFile,
} from '../../Components/FormRenderer';
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
        birthDate: string | null;
        nisn: string | null;
        originSchool: string | null;
        address: string | null;
        guardianName: string | null;
        guardianPhone: string | null;
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
    fields: FormFieldDef[];
    answers: Record<string, string | string[]>;
    files: Record<string, StoredFile>;
    statuses: Option[];
    can: { manage: boolean; cancel: boolean; enroll: boolean };
}

/** What an applicant's answer reads as: a list of choices joined, a stored file as a link, text as it is. */
function answerText(value: string | string[] | undefined): string {
    if (value === undefined) {
        return '';
    }

    return Array.isArray(value) ? value.join(', ') : value;
}

/** The data of one applicant, to read or correct. */
function DataPanel({
    applicant,
    waves,
    paths,
    fields,
    answers,
    files,
    editable,
}: Pick<
    ApplicantProps,
    'applicant' | 'waves' | 'paths' | 'fields' | 'answers' | 'files'
> & { editable: boolean }) {
    const form = useForm<ApplicantData>({
        wave_id: String(applicant.waveId),
        path_id: String(applicant.pathId),
        name: applicant.name,
        gender: applicant.gender,
        birth_place: applicant.birthPlace ?? '',
        birth_date: applicant.birthDate ?? '',
        nisn: applicant.nisn ?? '',
        origin_school: applicant.originSchool ?? '',
        address: applicant.address ?? '',
        guardian_name: applicant.guardianName ?? '',
        guardian_phone: applicant.guardianPhone ?? '',
        answers: initialAnswers(fields, answers),
    });
    const errors: Partial<Record<string, string>> = form.errors;
    const binding = formBinding(form.data, errors, (key, value) =>
        form.setData(key as keyof ApplicantData, value as never),
    );

    if (!editable) {
        const recorded: Record<string, string | null> = {
            path_id: applicant.pathName,
            name: applicant.name,
            gender: applicant.gender === 'L' ? 'Laki-laki' : 'Perempuan',
            nisn: applicant.nisn,
            birth_place: applicant.birthPlace,
            birth_date: applicant.birthDate,
            origin_school: applicant.originSchool,
            address: applicant.address,
            guardian_name: applicant.guardianName,
            guardian_phone: applicant.guardianPhone,
        };
        const rows: [string, ReactNode][] = [];

        // A field the period no longer asks is left out, unless this applicant
        // already answered it (from before it was archived).
        for (const field of fields) {
            if (field.type === 'section') {
                continue;
            }

            const file = files[String(field.id)];
            const value =
                field.key !== null
                    ? (recorded[field.key] ?? '')
                    : answerText(answers[String(field.id)]);

            if (field.type === 'file') {
                if (!field.archived || file !== undefined) {
                    rows.push([
                        field.label,
                        file === undefined ? (
                            '—'
                        ) : (
                            <a
                                href={file.url}
                                className="underline underline-offset-4"
                            >
                                {file.name}
                            </a>
                        ),
                    ]);
                }

                continue;
            }

            if (!field.archived || value !== '') {
                rows.push([field.label, value === '' ? '—' : value]);
            }
        }

        rows.push(['Gelombang', applicant.waveName ?? '—']);

        return (
            <Panel title="Data pendaftar">
                <DefinitionList rows={rows} />
            </Panel>
        );
    }

    return (
        <Panel title="Data pendaftar">
            <form
                className="flex flex-col gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    // A file in the form makes Inertia send multipart, which only POST carries: it says it is a PUT.
                    form.transform((data) => ({ ...data, _method: 'put' }));
                    form.post(update.url({ applicant: applicant.id }), {
                        preserveScroll: true,
                        onSuccess: () => form.setDefaults(),
                    });
                }}
            >
                <FormRenderer
                    fields={fields}
                    paths={paths}
                    storedFiles={files}
                    {...binding}
                    before={
                        <Field data-invalid={errors.wave_id !== undefined}>
                            <FieldLabel>Gelombang</FieldLabel>
                            <OptionSelect
                                label="Gelombang"
                                placeholder="Pilih gelombang"
                                value={form.data.wave_id}
                                onChange={(value) =>
                                    form.setData('wave_id', value)
                                }
                                options={waves}
                            />
                            {errors.wave_id !== undefined && (
                                <FieldError>{errors.wave_id}</FieldError>
                            )}
                        </Field>
                    }
                />
                <div>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        Simpan data
                    </Button>
                </div>
            </form>
        </Panel>
    );
}

/** The committee's check: waiting, needs correction (with a note), verified. */
function VerificationPanel({
    applicant,
    statuses,
}: Pick<ApplicantProps, 'applicant' | 'statuses'>) {
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
                    form.put(
                        updateVerification.url({ applicant: applicant.id }),
                        {
                            preserveScroll: true,
                            onSuccess: () => form.setDefaults(),
                        },
                    );
                }}
            >
                <Field data-invalid={errors.status !== undefined}>
                    <FieldLabel>Status</FieldLabel>
                    <OptionSelect
                        label="Status verifikasi"
                        value={form.data.status}
                        onChange={(value) => form.setData('status', value)}
                        options={statuses}
                    />
                    {errors.status !== undefined && (
                        <FieldError>{errors.status}</FieldError>
                    )}
                </Field>
                <Field data-invalid={errors.verification_note !== undefined}>
                    <FieldLabel htmlFor="verification-note">
                        Catatan untuk pendaftar
                    </FieldLabel>
                    <Textarea
                        id="verification-note"
                        value={form.data.verification_note}
                        onChange={(event) =>
                            form.setData(
                                'verification_note',
                                event.target.value,
                            )
                        }
                        placeholder="Wajib diisi bila statusnya Perlu perbaikan."
                        aria-invalid={errors.verification_note !== undefined}
                    />
                    {errors.verification_note !== undefined && (
                        <FieldError>{errors.verification_note}</FieldError>
                    )}
                </Field>
                <div>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
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
                    form.post(enroll.url({ applicant: applicant.id }), {
                        preserveScroll: true,
                    });
                }}
            >
                <p className="text-sm text-muted-foreground">
                    Catat daftar ulang setelah {applicant.name} datang
                    melengkapi berkas. Pendaftar menjadi siswa di Warga Sekolah
                    (belum berkelas; tempatkan di Akademik › Penempatan Siswa ›
                    Belum ditempatkan); NIS diberikan oleh sekolah.
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
                        onChange={(event) =>
                            form.setData('nis', event.target.value)
                        }
                        aria-invalid={errors.nis !== undefined}
                    />
                    {errors.nis !== undefined && (
                        <FieldError>{errors.nis}</FieldError>
                    )}
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
export default function ApplicantShow({
    applicant,
    period,
    waves,
    paths,
    fields,
    answers,
    files,
    statuses,
    can,
}: ApplicantProps) {
    const page = usePage<{ errors: Record<string, string> }>();
    const cancelError = page.props.errors.applicant;
    const status = statusOf(
        applicant.decision === 'pending'
            ? applicant.status
            : applicant.decision,
    );
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
                            Pendaftar ini sudah daftar ulang pada{' '}
                            {applicant.enrolledOn} dan menjadi siswa; datanya
                            tidak diubah dari sini.
                        </AlertDescription>
                    </Alert>
                )}
                {cancelError !== undefined && (
                    <Alert variant="destructive">
                        <AlertDescription>{cancelError}</AlertDescription>
                    </Alert>
                )}

                <DataPanel
                    applicant={applicant}
                    waves={waves}
                    paths={paths}
                    fields={fields}
                    answers={answers}
                    files={files}
                    editable={editable}
                />

                {editable && (
                    <VerificationPanel
                        applicant={applicant}
                        statuses={statuses}
                    />
                )}

                {(applicant.score !== null ||
                    applicant.decision !== 'pending') && (
                    <Panel title="Hasil seleksi">
                        <DefinitionList
                            rows={[
                                ['Nilai seleksi', applicant.score ?? '—'],
                                ['Keputusan', applicant.decisionLabel],
                            ]}
                        />
                    </Panel>
                )}

                {can.manage && can.enroll && (
                    <EnrollPanel applicant={applicant} />
                )}

                {can.manage && can.cancel && (
                    <Panel title="Batalkan pendaftaran">
                        <p className="mb-4 text-sm text-muted-foreground">
                            Untuk pendaftaran yang salah atau ganda. Pendaftaran
                            dihapus dan nomornya tidak dipakai lagi. Hanya bisa
                            selama belum ada keputusan seleksi.
                        </p>
                        <ConfirmAction
                            trigger={
                                <Button variant="destructive">
                                    Batalkan pendaftaran
                                </Button>
                            }
                            title={`Batalkan pendaftaran ${applicant.number}?`}
                            description={`Pendaftaran ${applicant.name} dihapus dan tidak bisa dikembalikan.`}
                            confirmLabel="Batalkan pendaftaran"
                            onConfirm={() =>
                                router.delete(
                                    destroy.url({ applicant: applicant.id }),
                                )
                            }
                        />
                    </Panel>
                )}
            </div>
        </PpdbPage>
    );
}
