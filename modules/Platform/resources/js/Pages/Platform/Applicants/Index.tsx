import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import {
    resend as resendInvitation,
    store as inviteApplicant,
} from '@/actions/Modules/Platform/App/Http/Controllers/ApplicantConsoleController';
import { show as showApplication } from '@/actions/Modules/Platform/App/Http/Controllers/ApplicationReviewController';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Button } from '@shared/components/ui/button';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';

import { consolePath } from '../../../Components/consolePath';
import {
    DataTable,
    EmptyState,
    PageHeader,
    Panel,
    StatusChip,
} from '../../../Components/ConsoleParts';
import ProviderLayout from '../../../Components/ProviderLayout';
import { send } from '../../../Components/send';

interface ApplicantRow {
    id: number;
    name: string;
    email: string;
    state: string;
    schoolName: string | null;
    applicationId: number | null;
    canResendInvitation: boolean;
    createdAt: string | null;
}

/**
 * Provider console: applicant accounts — people registering a school.
 * Inviting creates the account without a password and mails a link to
 * set one; from there the applicant goes through the usual onboarding.
 */
export default function ApplicantsIndex({
    applicants,
}: {
    applicants: ApplicantRow[];
}) {
    const form = useForm({ name: '', email: '' });
    const errors = form.errors as typeof form.errors & { applicant?: string };

    function invite(event: FormEvent) {
        event.preventDefault();

        form.post(consolePath(inviteApplicant.url()), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <ProviderLayout width="max-w-4xl">
            <Head title="Pemohon" />

            <PageHeader
                title="Pemohon"
                description="Akun orang yang mendaftarkan sekolah. Undang pemohon, atau biarkan mereka mendaftar sendiri."
            />

            {errors.applicant !== undefined && (
                <Alert variant="destructive" className="mt-6">
                    <AlertDescription>{errors.applicant}</AlertDescription>
                </Alert>
            )}

            <form onSubmit={invite} noValidate className="mt-8">
                <Panel title="Undang pemohon">
                    <FieldGroup>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field data-invalid={!!form.errors.name}>
                                <FieldLabel htmlFor="invite-name">
                                    Nama
                                </FieldLabel>
                                <Input
                                    id="invite-name"
                                    value={form.data.name}
                                    aria-invalid={!!form.errors.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    required
                                />
                                <FieldError>{form.errors.name}</FieldError>
                            </Field>
                            <Field data-invalid={!!form.errors.email}>
                                <FieldLabel htmlFor="invite-email">
                                    Email
                                </FieldLabel>
                                <Input
                                    id="invite-email"
                                    type="email"
                                    value={form.data.email}
                                    aria-invalid={!!form.errors.email}
                                    onChange={(event) =>
                                        form.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <FieldError>{form.errors.email}</FieldError>
                            </Field>
                        </div>
                    </FieldGroup>
                    <Button
                        type="submit"
                        className="mt-5"
                        disabled={form.processing}
                    >
                        {form.processing ? 'Mengirim...' : 'Kirim undangan'}
                    </Button>
                </Panel>
            </form>

            <div className="mt-8">
                {applicants.length === 0 ? (
                    <EmptyState>Belum ada pemohon.</EmptyState>
                ) : (
                    <DataTable
                        head={['Pemohon', 'Sekolah', 'Status', 'Terdaftar', '']}
                    >
                        {applicants.map((applicant) => (
                            <TableRow key={applicant.id}>
                                <TableCell>
                                    <p className="font-medium">
                                        {applicant.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {applicant.email}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    {applicant.applicationId !== null ? (
                                        <Link
                                            href={consolePath(
                                                showApplication.url({
                                                    application:
                                                        applicant.applicationId,
                                                }),
                                            )}
                                            className="hover:underline"
                                        >
                                            {applicant.schoolName}
                                        </Link>
                                    ) : (
                                        '—'
                                    )}
                                </TableCell>
                                <TableCell>
                                    <StatusChip status={applicant.state} />
                                </TableCell>
                                <TableCell>
                                    {applicant.createdAt ?? '—'}
                                </TableCell>
                                <TableCell className="text-right">
                                    {applicant.canResendInvitation && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                send(
                                                    'post',
                                                    resendInvitation.url({
                                                        applicant: applicant.id,
                                                    }),
                                                )
                                            }
                                        >
                                            Kirim ulang undangan
                                        </Button>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </DataTable>
                )}
            </div>
        </ProviderLayout>
    );
}
