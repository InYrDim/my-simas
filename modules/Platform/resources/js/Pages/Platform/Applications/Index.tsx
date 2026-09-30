import { Head, Link } from '@inertiajs/react';

import { show as showApplication } from '@/actions/Modules/Platform/App/Http/Controllers/ApplicationReviewController';
import { destroy as providerLogout } from '@/actions/Modules/Platform/App/Http/Controllers/Auth/ProviderAuthenticatedSessionController';

import type { ApplicationData } from '@/types/ApplicationData';

interface IndexProps {
    applications: ApplicationData[];
    status?: string;
}

/**
 * Provider console: pending school applications (oldest first). The
 * working list is pending only; decided rows are reachable via their
 * detail page for situational awareness.
 */
export default function ApplicationsIndex({
    applications,
    status,
}: IndexProps) {
    return (
        <div className="min-h-[100dvh] bg-zinc-950">
            <header className="flex h-16 items-center justify-between border-b border-zinc-800 px-6">
                <div className="flex items-center gap-6">
                    <span className="text-sm font-semibold text-zinc-100">
                        Console Provider
                    </span>

                    <Link
                        href="/platform"
                        className="text-sm text-zinc-400 transition-colors hover:text-zinc-200"
                    >
                        Beranda
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

            <Head title="Aplikasi Sekolah" />

            <main className="mx-auto max-w-4xl px-6 py-10">
                <h1 className="text-xl font-semibold text-zinc-100">
                    Pengajuan Sekolah
                </h1>

                <p className="mt-1 text-sm text-zinc-400">
                    Tinjau pengajuan, koreksi data bila perlu, lalu ACC atau
                    tolak.
                </p>

                {status !== undefined && status !== '' && (
                    <div className="mt-6 rounded-lg border border-emerald-800/50 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-300">
                        {status}
                    </div>
                )}

                {applications.length === 0 ? (
                    <div className="mt-10 rounded-lg border border-zinc-800 bg-zinc-900/50 px-6 py-10 text-center">
                        <p className="text-sm text-zinc-400">
                            Tidak ada pengajuan pending. Semua beres.
                        </p>
                    </div>
                ) : (
                    <ul className="mt-8 flex flex-col gap-3">
                        {applications.map((application) => (
                            <li key={application.id}>
                                <Link
                                    href={showApplication.url({
                                        application: application.id,
                                    })}
                                    className="block rounded-lg border border-zinc-800 bg-zinc-900/50 px-5 py-4 transition-colors hover:border-zinc-700 hover:bg-zinc-900"
                                >
                                    <div className="flex items-center justify-between gap-4">
                                        <div>
                                            <p className="font-medium text-zinc-100">
                                                {application.schoolName}
                                            </p>
                                            <p className="mt-0.5 text-sm text-zinc-400">
                                                {application.applicantName} ·{' '}
                                                {application.applicantEmail}
                                            </p>
                                        </div>

                                        <div className="text-right">
                                            <span className="rounded-full border border-amber-800/60 bg-amber-950/40 px-2.5 py-0.5 text-xs font-medium text-amber-300">
                                                pending
                                            </span>
                                            <p className="mt-1 font-mono text-xs text-zinc-500">
                                                /{application.desiredSlug}
                                            </p>
                                        </div>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </main>
        </div>
    );
}
