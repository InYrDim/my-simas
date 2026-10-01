import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { consolePath } from '../../../Components/consolePath';

import type { ApplicationData } from '@/types/ApplicationData';

import {
    approve as approveApplication,
    index as applicationsIndex,
    reject as rejectApplication,
} from '@/actions/Modules/Platform/App/Http/Controllers/ApplicationReviewController';

import ProviderLayout from '../../../Components/ProviderLayout';

interface ShowProps {
    application: ApplicationData;
}

const TIMEZONES = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'];

const isPending = (status: string) => status === 'pending';

/**
 * Provider console: review one school application. The approve form is
 * pre-filled with the submitted data and editable — the corrected
 * payload becomes the FINAL tenant data (grill Q9). Reject asks for a
 * confirmation note. Decided applications render read-only.
 */
export default function ApplicationsShow({ application }: ShowProps) {
    const decideForm = useForm({
        school_name: application.schoolName,
        desired_slug: application.desiredSlug,
        timezone: application.timezone,
        admin_note: '',
    });

    const [rejecting, setRejecting] = useState(false);

    function approve(event: FormEvent) {
        event.preventDefault();

        decideForm.post(
            consolePath(approveApplication.url({ application: application.id })),
        );
    }

    function reject(event: FormEvent) {
        event.preventDefault();

        decideForm.post(consolePath(rejectApplication.url({ application: application.id })));
    }

    return (
        <ProviderLayout width="max-w-2xl">
            <Head title={`${application.schoolName} — Review`} />

            <Link
                href={consolePath(applicationsIndex.url())}
                className="inline-flex min-h-11 items-center text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                Kembali ke pengajuan
            </Link>

            <div>
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-foreground">
                        {application.schoolName}
                    </h1>

                    <span
                        className={
                            isPending(application.status)
                                ? 'rounded-md border border-accent/40 bg-accent/10 px-2.5 py-0.5 text-xs font-medium text-foreground'
                                : 'rounded-md border border-input bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground'
                        }
                    >
                        {application.status}
                    </span>
                </div>

                <dl className="mt-6 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 rounded-lg border border-border bg-card px-5 py-4 text-sm">
                    <dt className="text-muted-foreground">Pengaju</dt>
                    <dd className="text-foreground">
                        {application.applicantName} (
                        {application.applicantEmail})
                    </dd>

                    <dt className="text-muted-foreground">Slug diajukan</dt>
                    <dd className="font-mono text-foreground">
                        /{application.desiredSlug}
                    </dd>

                    {application.applicantMessage !== null && (
                        <>
                            <dt className="text-muted-foreground">Pesan</dt>
                            <dd className="text-foreground/80">
                                {application.applicantMessage}
                            </dd>
                        </>
                    )}

                    {application.adminNote !== null && (
                        <>
                            <dt className="text-muted-foreground">Catatan</dt>
                            <dd className="text-foreground/80">
                                {application.adminNote}
                            </dd>
                        </>
                    )}
                </dl>

                {decideForm.errors.application !== undefined && (
                    <div className="mt-6 rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        {decideForm.errors.application}
                    </div>
                )}

                {isPending(application.status) ? (
                    <>
                        <form
                            onSubmit={approve}
                            className="mt-8 flex flex-col gap-4 rounded-lg border border-border bg-card p-5"
                            noValidate
                        >
                            <h2 className="text-sm font-semibold text-foreground">
                                Setujui — koreksi data bila perlu
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Data di form ini menjadi data final tenant.
                            </p>

                            <label className="flex flex-col gap-1.5 text-sm">
                                <span className="text-muted-foreground">
                                    Nama sekolah
                                </span>
                                <input
                                    type="text"
                                    name="school_name"
                                    value={decideForm.data.school_name}
                                    onChange={(event) =>
                                        decideForm.setData(
                                            'school_name',
                                            event.target.value,
                                        )
                                    }
                                    className="rounded-lg border border-input bg-card px-3 py-2 text-foreground focus:border-ring focus:outline-none"
                                    required
                                />
                                {decideForm.errors.school_name !==
                                    undefined && (
                                    <span className="text-xs text-destructive">
                                        {decideForm.errors.school_name}
                                    </span>
                                )}
                            </label>

                            <label className="flex flex-col gap-1.5 text-sm">
                                <span className="text-muted-foreground">
                                    Slug sekolah
                                </span>
                                <input
                                    type="text"
                                    name="desired_slug"
                                    value={decideForm.data.desired_slug}
                                    onChange={(event) =>
                                        decideForm.setData(
                                            'desired_slug',
                                            event.target.value,
                                        )
                                    }
                                    className="rounded-lg border border-input bg-card px-3 py-2 font-mono text-foreground focus:border-ring focus:outline-none"
                                    required
                                />
                                {decideForm.errors.desired_slug !==
                                    undefined && (
                                    <span className="text-xs text-destructive">
                                        {decideForm.errors.desired_slug}
                                    </span>
                                )}
                            </label>

                            <label className="flex flex-col gap-1.5 text-sm">
                                <span className="text-muted-foreground">
                                    Zona waktu
                                </span>
                                <select
                                    name="timezone"
                                    value={decideForm.data.timezone}
                                    onChange={(event) =>
                                        decideForm.setData(
                                            'timezone',
                                            event.target.value,
                                        )
                                    }
                                    className="rounded-lg border border-input bg-card px-3 py-2 text-foreground focus:border-ring focus:outline-none"
                                >
                                    {TIMEZONES.map((timezone) => (
                                        <option key={timezone} value={timezone}>
                                            {timezone}
                                        </option>
                                    ))}
                                </select>
                            </label>

                            <label className="flex flex-col gap-1.5 text-sm">
                                <span className="text-muted-foreground">
                                    Catatan (opsional)
                                </span>
                                <textarea
                                    name="admin_note"
                                    value={decideForm.data.admin_note}
                                    onChange={(event) =>
                                        decideForm.setData(
                                            'admin_note',
                                            event.target.value,
                                        )
                                    }
                                    rows={2}
                                    className="rounded-lg border border-input bg-card px-3 py-2 text-foreground focus:border-ring focus:outline-none"
                                />
                            </label>

                            <button
                                type="submit"
                                disabled={decideForm.processing}
                                className="mt-1 w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {decideForm.processing
                                    ? 'Memproses...'
                                    : 'Setujui & buat sekolah'}
                            </button>
                        </form>

                        {rejecting ? (
                            <form
                                onSubmit={reject}
                                className="mt-4 flex flex-col gap-4 rounded-lg border border-destructive/30 bg-destructive/5 p-5"
                                noValidate
                            >
                                <h2 className="text-sm font-semibold text-destructive">
                                    Tolak pengajuan ini?
                                </h2>

                                <label className="flex flex-col gap-1.5 text-sm">
                                    <span className="text-muted-foreground">
                                        Alasan (opsional)
                                    </span>
                                    <textarea
                                        name="reject_note"
                                        value={decideForm.data.admin_note}
                                        onChange={(event) =>
                                            decideForm.setData(
                                                'admin_note',
                                                event.target.value,
                                            )
                                        }
                                        rows={2}
                                        className="rounded-lg border border-input bg-card px-3 py-2 text-foreground focus:border-destructive focus:outline-none"
                                    />
                                </label>

                                <div className="flex gap-3">
                                    <button
                                        type="submit"
                                        disabled={decideForm.processing}
                                        className="rounded-lg bg-destructive px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-destructive/90 disabled:opacity-60"
                                    >
                                        Ya, tolak
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => setRejecting(false)}
                                        className="rounded-lg border border-input px-4 py-2 text-sm text-foreground/80 transition-colors hover:bg-muted"
                                    >
                                        Batal
                                    </button>
                                </div>
                            </form>
                        ) : (
                            <button
                                type="button"
                                onClick={() => setRejecting(true)}
                                className="mt-4 text-sm text-destructive transition-colors hover:text-destructive"
                            >
                                Tolak pengajuan...
                            </button>
                        )}
                    </>
                ) : (
                    <p className="mt-8 rounded-lg border border-border bg-card px-5 py-4 text-sm text-muted-foreground">
                        Pengajuan ini sudah diputuskan ({application.status}
                        {application.decidedAt !== null
                            ? `, ${application.decidedAt}`
                            : ''}
                        ). Tidak ada aksi lagi.
                    </p>
                )}
            </div>
        </ProviderLayout>
    );
}
