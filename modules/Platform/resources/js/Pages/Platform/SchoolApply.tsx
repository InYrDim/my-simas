import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { AuthInput } from '@shared/components/AuthInput';

import { store as schoolApplyStore } from '@/actions/Modules/Platform/App/Http/Controllers/SchoolApplyController';

interface SchoolApplyProps {
    timezones: string[];
    status?: string;
}

/**
 * Public school application (central host, no auth). The applicant is
 * NOT a user: approval provisions the tenant AND their admin account
 * via an emailed set-password link (Stage 8) — so there is no password
 * field here by design. The hidden "website" field is a honeypot:
 * humans never see it; bots that fill it are silently dropped.
 */
export default function SchoolApply({ timezones, status }: SchoolApplyProps) {
    const form = useForm({
        school_name: '',
        desired_slug: '',
        timezone: timezones[0] ?? 'Asia/Jakarta',
        applicant_name: '',
        applicant_email: '',
        applicant_message: '',
        website: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(schoolApplyStore.url());
    }

    return (
        <div className="flex min-h-[100dvh] flex-col items-center justify-center bg-zinc-100 px-4 py-10">
            <Head title="Pendaftaran Sekolah" />

            <div className="w-full max-w-md">
                <div className="rounded-xl border border-zinc-200 bg-white px-8 py-8 shadow-sm">
                    <p className="mb-1 text-sm text-zinc-500">
                        SIMAS — Sistem Informasi Manajemen Sekolah
                    </p>

                    <h1 className="text-xl font-semibold text-zinc-900">
                        Daftarkan sekolah Anda
                    </h1>

                    <p className="mt-1 text-sm text-zinc-500">
                        Tim kami akan meninjau pengajuan dan mengirim email
                        aktivasi ke admin sekolah setelah disetujui.
                    </p>

                    {status !== undefined && status !== '' ? (
                        <div className="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            {status}
                        </div>
                    ) : null}

                    {form.errors.application !== undefined && (
                        <div className="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {form.errors.application}
                        </div>
                    )}

                    <form
                        onSubmit={submit}
                        className="mt-6 flex flex-col gap-4"
                        noValidate
                    >
                        <AuthInput
                            label="Nama sekolah"
                            id="school_name"
                            name="school_name"
                            type="text"
                            autoFocus
                            required
                            value={form.data.school_name}
                            error={form.errors.school_name}
                            onChange={(event) =>
                                form.setData('school_name', event.target.value)
                            }
                        />

                        <AuthInput
                            label="Slug sekolah"
                            id="desired_slug"
                            name="desired_slug"
                            type="text"
                            required
                            hint="Kecil, tanpa spasi. Contoh: sma-nusantara"
                            value={form.data.desired_slug}
                            error={form.errors.desired_slug}
                            onChange={(event) =>
                                form.setData('desired_slug', event.target.value)
                            }
                        />

                        <label className="flex flex-col gap-1.5 text-sm">
                            <span className="font-medium text-zinc-700">
                                Zona waktu
                            </span>
                            <select
                                name="timezone"
                                value={form.data.timezone}
                                onChange={(event) =>
                                    form.setData('timezone', event.target.value)
                                }
                                className="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 focus:border-zinc-500 focus:outline-none"
                            >
                                {timezones.map((timezone) => (
                                    <option key={timezone} value={timezone}>
                                        {timezone}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <div className="my-1 border-t border-zinc-100" />

                        <AuthInput
                            label="Nama Anda"
                            id="applicant_name"
                            name="applicant_name"
                            type="text"
                            autoComplete="name"
                            required
                            value={form.data.applicant_name}
                            error={form.errors.applicant_name}
                            onChange={(event) =>
                                form.setData(
                                    'applicant_name',
                                    event.target.value,
                                )
                            }
                        />

                        <AuthInput
                            label="Email"
                            id="applicant_email"
                            name="applicant_email"
                            type="email"
                            autoComplete="email"
                            required
                            hint="Email admin sekolah — tautan aktivasi dikirim ke sini."
                            value={form.data.applicant_email}
                            error={form.errors.applicant_email}
                            onChange={(event) =>
                                form.setData(
                                    'applicant_email',
                                    event.target.value,
                                )
                            }
                        />

                        <label className="flex flex-col gap-1.5 text-sm">
                            <span className="font-medium text-zinc-700">
                                Pesan (opsional)
                            </span>
                            <textarea
                                name="applicant_message"
                                value={form.data.applicant_message}
                                onChange={(event) =>
                                    form.setData(
                                        'applicant_message',
                                        event.target.value,
                                    )
                                }
                                rows={3}
                                className="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 focus:border-zinc-500 focus:outline-none"
                            />
                        </label>

                        {/* Honeypot: visually + programmatically hidden
                            from humans; tab order excluded. */}
                        <div className="hidden" aria-hidden="true">
                            <label>
                                Website
                                <input
                                    type="text"
                                    name="website"
                                    tabIndex={-1}
                                    autoComplete="off"
                                    value={form.data.website}
                                    onChange={(event) =>
                                        form.setData(
                                            'website',
                                            event.target.value,
                                        )
                                    }
                                />
                            </label>
                        </div>

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="mt-2 rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-zinc-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {form.processing
                                ? 'Mengirim...'
                                : 'Kirim pengajuan'}
                        </button>
                    </form>
                </div>

                <p className="mt-4 text-center text-xs text-zinc-400">
                    Pengajuan tidak membuat akun — akun admin dibuat setelah
                    pengajuan disetujui.
                </p>
            </div>
        </div>
    );
}
