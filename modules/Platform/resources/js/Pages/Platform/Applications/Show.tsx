import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

import {
    approve as approveApplication,
    index as applicationsIndex,
    reject as rejectApplication,
} from '@/actions/Modules/Platform/App/Http/Controllers/ApplicationReviewController';
import type { ApplicationData } from '../../../types/ApplicationData';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Button } from '@shared/components/ui/button';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import { Textarea } from '@shared/components/ui/textarea';

import { consolePath } from '../../../Components/consolePath';
import {
    DefinitionList,
    OptionSelect,
    PageHeader,
    Panel,
    StatusChip,
} from '../../../Components/ConsoleParts';
import ProviderLayout from '../../../Components/ProviderLayout';

interface ShowProps {
    application: ApplicationData;
    /** Selectable plans; the provider may correct the applicant's choice. */
    plans: { key: string; name: string }[];
}

const TIMEZONES = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'];

const isPending = (status: string) => status === 'pending';

/**
 * Provider console: review one school application. The approve form is
 * pre-filled with the submitted data and editable — the corrected
 * payload becomes the FINAL tenant data (grill Q9). Reject asks for a
 * confirmation note. Decided applications render read-only.
 */
export default function ApplicationsShow({ application, plans }: ShowProps) {
    const planName = (key: string | null) =>
        key === null
            ? 'Paket trial default'
            : (plans.find((plan) => plan.key === key)?.name ?? key);

    const decideForm = useForm({
        school_name: application.schoolName,
        desired_slug: application.desiredSlug,
        timezone: application.timezone,
        plan_key: application.planKey ?? '',
        admin_note: '',
    });

    const [rejecting, setRejecting] = useState(false);

    function approve(event: FormEvent) {
        event.preventDefault();

        decideForm.post(
            consolePath(
                approveApplication.url({ application: application.id }),
            ),
        );
    }

    function reject(event: FormEvent) {
        event.preventDefault();

        decideForm.post(
            consolePath(rejectApplication.url({ application: application.id })),
        );
    }

    const rows: [string, ReactNode][] = [
        [
            'Pengaju',
            `${application.applicantName} (${application.applicantEmail})`,
        ],
        [
            'Slug diajukan',
            <span key="slug" className="font-mono">
                /{application.desiredSlug}
            </span>,
        ],
        ['Paket dipilih', planName(application.planKey)],
    ];

    if (application.applicantId === null) {
        rows.push(['Akun pemohon', 'Tidak ada (pengajuan lama, tanpa akun)']);
    }

    if (application.applicantMessage !== null) {
        rows.push(['Pesan', application.applicantMessage]);
    }

    if (application.adminNote !== null) {
        rows.push(['Catatan', application.adminNote]);
    }

    return (
        <ProviderLayout width="max-w-2xl">
            <Head title={`${application.schoolName} — Review`} />

            <Button asChild variant="link" className="px-0">
                <Link href={consolePath(applicationsIndex.url())}>
                    Kembali ke pengajuan
                </Link>
            </Button>

            <PageHeader
                title={application.schoolName}
                actions={<StatusChip status={application.status} />}
            />

            <Panel className="mt-6">
                <DefinitionList rows={rows} />
            </Panel>

            {(decideForm.errors as Record<string, string>).application !==
                undefined && (
                <Alert variant="destructive" className="mt-6">
                    <AlertDescription>
                        {
                            (decideForm.errors as Record<string, string>)
                                .application
                        }
                    </AlertDescription>
                </Alert>
            )}

            {isPending(application.status) ? (
                <>
                    <form onSubmit={approve} noValidate className="mt-8">
                        <Panel title="Setujui — koreksi data bila perlu">
                            <p className="mb-4 text-xs text-muted-foreground">
                                Data di form ini menjadi data final tenant.
                            </p>
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        !!decideForm.errors.school_name
                                    }
                                >
                                    <FieldLabel htmlFor="school-name">
                                        Nama sekolah
                                    </FieldLabel>
                                    <Input
                                        id="school-name"
                                        value={decideForm.data.school_name}
                                        onChange={(event) =>
                                            decideForm.setData(
                                                'school_name',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            !!decideForm.errors.school_name
                                        }
                                        required
                                    />
                                    <FieldError>
                                        {decideForm.errors.school_name}
                                    </FieldError>
                                </Field>
                                <Field
                                    data-invalid={
                                        !!decideForm.errors.desired_slug
                                    }
                                >
                                    <FieldLabel htmlFor="school-slug">
                                        Slug sekolah
                                    </FieldLabel>
                                    <Input
                                        id="school-slug"
                                        value={decideForm.data.desired_slug}
                                        onChange={(event) =>
                                            decideForm.setData(
                                                'desired_slug',
                                                event.target.value,
                                            )
                                        }
                                        className="font-mono"
                                        aria-invalid={
                                            !!decideForm.errors.desired_slug
                                        }
                                        required
                                    />
                                    <FieldError>
                                        {decideForm.errors.desired_slug}
                                    </FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel>Zona waktu</FieldLabel>
                                    <OptionSelect
                                        label="Zona waktu"
                                        value={decideForm.data.timezone}
                                        onChange={(timezone) =>
                                            decideForm.setData(
                                                'timezone',
                                                timezone,
                                            )
                                        }
                                        options={TIMEZONES.map((timezone) => ({
                                            value: timezone,
                                            label: timezone,
                                        }))}
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel>
                                        Paket (trial dimulai di paket ini)
                                    </FieldLabel>
                                    <OptionSelect
                                        label="Paket"
                                        allLabel={
                                            application.planKey === null
                                                ? 'Paket trial default'
                                                : undefined
                                        }
                                        value={decideForm.data.plan_key}
                                        onChange={(planKey) =>
                                            decideForm.setData(
                                                'plan_key',
                                                planKey,
                                            )
                                        }
                                        options={plans.map((plan) => ({
                                            value: plan.key,
                                            label: plan.name,
                                        }))}
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="admin-note">
                                        Catatan (opsional)
                                    </FieldLabel>
                                    <Textarea
                                        id="admin-note"
                                        value={decideForm.data.admin_note}
                                        onChange={(event) =>
                                            decideForm.setData(
                                                'admin_note',
                                                event.target.value,
                                            )
                                        }
                                        rows={2}
                                    />
                                </Field>
                            </FieldGroup>
                            <Button
                                type="submit"
                                className="mt-5 w-full"
                                disabled={decideForm.processing}
                            >
                                {decideForm.processing
                                    ? 'Memproses...'
                                    : 'Setujui & buat sekolah'}
                            </Button>
                        </Panel>
                    </form>

                    {rejecting ? (
                        <form onSubmit={reject} noValidate className="mt-4">
                            <Panel title="Tolak pengajuan ini?">
                                <Field>
                                    <FieldLabel htmlFor="reject-note">
                                        Alasan (opsional)
                                    </FieldLabel>
                                    <Textarea
                                        id="reject-note"
                                        value={decideForm.data.admin_note}
                                        onChange={(event) =>
                                            decideForm.setData(
                                                'admin_note',
                                                event.target.value,
                                            )
                                        }
                                        rows={2}
                                    />
                                </Field>
                                <div className="mt-4 flex gap-3">
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={decideForm.processing}
                                    >
                                        Ya, tolak
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setRejecting(false)}
                                    >
                                        Batal
                                    </Button>
                                </div>
                            </Panel>
                        </form>
                    ) : (
                        <Button
                            type="button"
                            variant="ghost"
                            className="mt-4 text-destructive"
                            onClick={() => setRejecting(true)}
                        >
                            Tolak pengajuan...
                        </Button>
                    )}
                </>
            ) : (
                <Panel className="mt-8">
                    <p className="text-sm text-muted-foreground">
                        Pengajuan ini sudah diputuskan ({application.status}
                        {application.decidedAt !== null
                            ? `, ${application.decidedAt}`
                            : ''}
                        ). Tidak ada aksi lagi.
                    </p>
                </Panel>
            )}
        </ProviderLayout>
    );
}
