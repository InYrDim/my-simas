import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import type { ApplicationData } from '@/types/ApplicationData';

import {
    approve as approveApplication,
    reject as rejectApplication,
} from '@/actions/Modules/Platform/App/Http/Controllers/ApplicationReviewController';

import { destroy as providerLogout } from '@/actions/Modules/Platform/App/Http/Controllers/Auth/ProviderAuthenticatedSessionController';

interface ShowProps {
    application: ApplicationData;
}

const TIMEZONES = [
    'Asia/Jakarta',
    'Asia/Makassar',
    'Asia/Jayapura',
];

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

        decideForm.post(approveApplication.url({ application: application.id }));
    }

    function reject(event: FormEvent) {
        event.preventDefault();

        decideForm.post(rejectApplication.url({ application: application.id }));
    }

    return (
        <div className="min-h-[100dvh] bg-zinc-950">
            <header className="flex h-16 items-center justify-between border-b border-zinc-800 px-6">
                <div className="flex items-center gap-6">
                    <span className="text-sm font-semibold text-zinc-100">
                        Console Provider
                    </span>

                    <Link
                        href="/platform/applications"
                        className="text-sm text-zinc-400 transition-colors hover:text-zinc-200"
                    >
                        ← Pengajuan
                    </Link>
                </div>

                <Link
                    href={providerLogout.url()}
                    method="post"
                    as="button"
                    className="text-sm text-zinc-400 transition-colors hover:text-zinc-200"
                >
                    Keluar
                </Link>
            </header>

            <Head title={`${application.schoolName} — Review`} />

            <main className="mx-auto max-w-2xl px-6 py-10">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-zinc-100">
                        {application.schoolName}
                    </h1>

                    <span
                        className={
                            isPending(application.status)
                                ? 'rounded-full border border-amber-800/60 bg-amber-950/40 px-2.5 py-0.5 text-xs font-medium text-amber-300'
                                : 'rounded-full border border-zinc-700 bg-zinc-900 px-2.5 py-0.5 text-xs font-medium text-zinc-400'
                        }
                    >
                        {application.status}
                    </span>
                </div>

                <dl className="mt-6 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 rounded-lg border border-zinc-800 bg-zinc-900/50 px-5 py-4 text-sm">
                    <dt className="text-zinc-500">Pengaju</dt>
                    <dd className="text-zinc-200">
                        {application.applicantName} ({application.applicantEmail})
                    </dd>

                    <dt className="text-zinc-500">Slug diajukan</dt>
                    <dd className="font-mono text-zinc-200">
                        /{application.desiredSlug}
                    </dd>

                    {application.applicantMessage !== null && (
                        <>
                            <dt className="text-zinc-500">Pesan</dt>
                            <dd className="text-zinc-300">
                                {application.applicantMessage}
                            </dd>
                        </>
                    )}

                    {application.adminNote !== null && (
                        <>
                            <dt className="text-zinc-500">Catatan</dt>
                            <dd className="text-zinc-300">
                                {application.adminNote}
                            </dd>
                        </>
                    )}
                </dl>

                {decideForm.errors.application !== undefined && (
                    <div className="mt-6 rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">
                        {decideForm.errors.application}
                    </div>
                )}

                {isPending(application.status) ? (
                    <>
                        <form
                            onSubmit={approve}
                            className="mt-8 flex flex-col gap-4 rounded-lg border border-zinc-800 bg-zinc-900/50 p-5"
                            noValidate
                        >
                            <h2 className="text-sm font-semibold text-zinc-100">
                                Setujui — koreksi data bila perlu
                            </h2>
                            <p className="text-xs text-zinc-500">
                                Data di form ini menjadi data final tenant.
                            </p>

                            <label className="flex flex-col gap-1.5 text-sm">
                                <span className="text-zinc-400">Nama sekolah</span>
                                <input
                                    type="text"
                                    name="school_name"
                                    value={decideForm.data.school_name}
                                    onChange={(event) => decideForm.setData('school_name', event.target.value)}
                                    className="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-zinc-100 focus:border-emerald-600 focus:outline-none"
                                    required
                                />
                                {decideForm.errors.school_name !== undefined && (
                                    <span className="text-xs text-red-400">{decideForm.errors.school_name}</span>
                                )}
                            </label>

                            <label className="flex flex-col gap-1.5 text-sm">
                                <span className="text-zinc-400">Slug (subdomain)</span>
                                <input
                                    type="text"
                                    name="desired_slug"
                                    value={decideForm.data.desired_slug}
                                    onChange={(event) => decideForm.setData('desired_slug', event.target.value)}
                                    className="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 font-mono text-zinc-100 focus:border-emerald-600 focus:outline-none"
                                    required
                                />
                                {decideForm.errors.desired_slug !== undefined && (
                                    <span className="text-xs text-red-400">{decideForm.errors.desired_slug}</span>
                                )}
                            </label>

                            <label className="flex flex-col gap-1.5 text-sm">
                                <span className="text-zinc-400">Zona waktu</span>
                                <select
                                    name="timezone"
                                    value={decideForm.data.timezone}
                                    onChange={(event) => decideForm.setData('timezone', event.target.value)}
                                    className="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-zinc-100 focus:border-emerald-600 focus:outline-none"
                                >
                                    {TIMEZONES.map((timezone) => (
                                        <option key={timezone} value={timezone}>
                                            {timezone}
                                        </option>
                                    ))}
                                </select>
                            </label>

                            <label className="flex flex-col gap-1.5 text-sm">
                                <span className="text-zinc-400">Catatan (opsional)</span>
                                <textarea
                                    name="admin_note"
                                    value={decideForm.data.admin_note}
                                    onChange={(event) => decideForm.setData('admin_note', event.target.value)}
                                    rows={2}
                                    className="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-zinc-100 focus:border-emerald-600 focus:outline-none"
                                />
                            </label>

                            <button
                                type="submit"
                                disabled={decideForm.processing}
                                className="mt-1 w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {decideForm.processing ? 'Memproses...' : 'Setujui & buat sekolah'}
                            </button>
                        </form>

                        {rejecting ? (
                            <form
                                onSubmit={reject}
                                className="mt-4 flex flex-col gap-4 rounded-lg border border-red-900/50 bg-red-950/20 p-5"
                                noValidate
                            >
                                <h2 className="text-sm font-semibold text-red-200">
                                    Tolak pengajuan ini?
                                </h2>

                                <label className="flex flex-col gap-1.5 text-sm">
                                    <span className="text-zinc-400">Alasan (opsional)</span>
                                    <textarea
                                        name="reject_note"
                                        value={decideForm.data.admin_note}
                                        onChange={(event) => decideForm.setData('admin_note', event.target.value)}
                                        rows={2}
                                        className="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-zinc-100 focus:border-red-600 focus:outline-none"
                                    />
                                </label>

                                <div className="flex gap-3">
                                    <button
                                        type="submit"
                                        disabled={decideForm.processing}
                                        className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-500 disabled:opacity-60"
                                    >
                                        Ya, tolak
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => setRejecting(false)}
                                        className="rounded-lg border border-zinc-700 px-4 py-2 text-sm text-zinc-300 transition-colors hover:bg-zinc-900"
                                    >
                                        Batal
                                    </button>
                                </div>
                            </form>
                        ) : (
                            <button
                                type="button"
                                onClick={() => setRejecting(true)}
                                className="mt-4 text-sm text-red-400 transition-colors hover:text-red-300"
                            >
                                Tolak pengajuan...
                            </button>
                        )}
                    </>
                ) : (
                    <p className="mt-8 rounded-lg border border-zinc-800 bg-zinc-900/50 px-5 py-4 text-sm text-zinc-400">
                        Pengajuan ini sudah diputuskan ({application.status}
                        {application.decidedAt !== null ? `, ${application.decidedAt}` : ''}).
                        Tidak ada aksi lagi.
                    </p>
                )}
            </main>
        </div>
    );
}
