import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { store } from '@/actions/Modules/Platform/App/Http/Controllers/Applicant/OnboardingController';
import { destroy as logout } from '@/actions/Modules/Platform/App/Http/Controllers/Applicant/SessionController';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@shared/components/ui/select';
import { Textarea } from '@shared/components/ui/textarea';

import ApplicantShell from '../../../Components/ApplicantShell';

interface Application {
    schoolName: string;
    desiredSlug: string;
    timezone: string;
    status: 'pending' | 'approved' | 'rejected';
    adminNote: string | null;
}

const statusLabels: Record<Application['status'], string> = {
    pending: 'Menunggu persetujuan',
    approved: 'Disetujui',
    rejected: 'Ditolak',
};

function LogoutButton() {
    return (
        <Button variant="outline" onClick={() => router.post(logout.url())}>
            Keluar
        </Button>
    );
}

/** The submitted school and where its review stands. */
function ApplicationStatus({ application }: { application: Application }) {
    return (
        <div className="flex flex-col gap-4">
            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                <dt className="text-muted-foreground">Sekolah</dt>
                <dd className="font-medium">{application.schoolName}</dd>
                <dt className="text-muted-foreground">Kode sekolah</dt>
                <dd>
                    <code>{application.desiredSlug}</code>
                </dd>
                <dt className="text-muted-foreground">Zona waktu</dt>
                <dd>{application.timezone}</dd>
                <dt className="text-muted-foreground">Status</dt>
                <dd>
                    <Badge
                        variant={application.status === 'approved' ? 'default' : 'outline'}
                    >
                        {statusLabels[application.status]}
                    </Badge>
                </dd>
            </dl>

            <p className="text-sm text-muted-foreground">
                {application.status === 'pending'
                    ? 'Tim kami sedang meninjau pengajuan Anda. Kami mengabari lewat email setelah ada keputusan.'
                    : 'Sekolah Anda sudah disetujui. Periksa email Anda untuk langkah masuk ke sekolah.'}
            </p>

            <LogoutButton />
        </div>
    );
}

/**
 * Onboarding: fill in the school and submit it for provider review, then
 * follow its status. A rejected application shows the provider's note and
 * the form again, pre-filled, so it can be corrected and resubmitted.
 */
export default function Onboarding({
    applicant,
    timezones,
    application,
}: {
    applicant: { name: string; email: string };
    timezones: string[];
    application: Application | null;
}) {
    const rejected = application?.status === 'rejected';

    const form = useForm({
        school_name: rejected ? application.schoolName : '',
        desired_slug: rejected ? application.desiredSlug : '',
        timezone: rejected ? application.timezone : (timezones[0] ?? 'Asia/Jakarta'),
        applicant_message: '',
    });

    const errors = form.errors as typeof form.errors & { application?: string };

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(store.url());
    }

    if (application !== null && !rejected) {
        return (
            <ApplicantShell
                title="Pengajuan sekolah"
                description={`Masuk sebagai ${applicant.name} (${applicant.email}).`}
                width="max-w-md"
            >
                <ApplicationStatus application={application} />
            </ApplicantShell>
        );
    }

    return (
        <ApplicantShell
            title="Data sekolah"
            description={`Masuk sebagai ${applicant.name} (${applicant.email}). Isi data sekolah, lalu ajukan untuk ditinjau.`}
            width="max-w-md"
        >
            <form onSubmit={submit} noValidate>
                <FieldGroup>
                    {rejected && (
                        <Alert variant="destructive">
                            <AlertDescription>
                                Pengajuan sebelumnya ditolak
                                {application.adminNote !== null && application.adminNote !== ''
                                    ? `: ${application.adminNote}`
                                    : '.'}{' '}
                                Perbaiki datanya lalu ajukan ulang.
                            </AlertDescription>
                        </Alert>
                    )}

                    {errors.application !== undefined && (
                        <Alert variant="destructive">
                            <AlertDescription>{errors.application}</AlertDescription>
                        </Alert>
                    )}

                    <Field data-invalid={!!form.errors.school_name}>
                        <FieldLabel htmlFor="school_name">Nama sekolah</FieldLabel>
                        <Input
                            id="school_name"
                            name="school_name"
                            autoFocus
                            required
                            value={form.data.school_name}
                            aria-invalid={!!form.errors.school_name}
                            onChange={(event) => form.setData('school_name', event.target.value)}
                        />
                        <FieldError>{form.errors.school_name}</FieldError>
                    </Field>

                    <Field data-invalid={!!form.errors.desired_slug}>
                        <FieldLabel htmlFor="desired_slug">Kode sekolah</FieldLabel>
                        <Input
                            id="desired_slug"
                            name="desired_slug"
                            required
                            value={form.data.desired_slug}
                            aria-invalid={!!form.errors.desired_slug}
                            onChange={(event) => form.setData('desired_slug', event.target.value)}
                        />
                        <FieldDescription>
                            Huruf kecil, angka, dan tanda hubung. Contoh: sma-nusantara
                        </FieldDescription>
                        <FieldError>{form.errors.desired_slug}</FieldError>
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="timezone">Zona waktu</FieldLabel>
                        <Select
                            value={form.data.timezone}
                            onValueChange={(value) => form.setData('timezone', value)}
                        >
                            <SelectTrigger id="timezone">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {timezones.map((timezone) => (
                                    <SelectItem key={timezone} value={timezone}>
                                        {timezone}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="applicant_message">Pesan (opsional)</FieldLabel>
                        <Textarea
                            id="applicant_message"
                            name="applicant_message"
                            rows={3}
                            value={form.data.applicant_message}
                            onChange={(event) =>
                                form.setData('applicant_message', event.target.value)
                            }
                        />
                    </Field>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing
                            ? 'Mengirim...'
                            : rejected
                              ? 'Ajukan ulang'
                              : 'Ajukan sekolah'}
                    </Button>

                    <LogoutButton />
                </FieldGroup>
            </form>
        </ApplicantShell>
    );
}
