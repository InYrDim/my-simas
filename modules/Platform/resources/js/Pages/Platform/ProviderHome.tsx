import { Head, Link } from '@inertiajs/react';

import { destroy as providerLogout } from '@/actions/Modules/Platform/App/Http/Controllers/Auth/ProviderAuthenticatedSessionController';

/**
 * Provider console landing (post-login). Minimal in Fase 1: real
 * tenant administration arrives in a later fase.
 */
export default function ProviderHome() {
    return (
        <div className="flex min-h-[100dvh] flex-col bg-zinc-950">
            <header className="flex h-16 items-center justify-between border-b border-zinc-800 px-6">
                <span className="text-sm font-semibold text-zinc-100">
                    Console Provider
                </span>

                <Link
                    href={providerLogout.url()}
                    method="post"
                    as="button"
                    className="text-sm text-zinc-400 transition-colors hover:text-zinc-200"
                >
                    Keluar
                </Link>
            </header>

            <main className="flex flex-1 items-center justify-center px-6">
                <div className="text-center">
                    <Head title="Console Provider" />
                    <h1 className="text-xl font-semibold text-zinc-100">
                        Selamat datang
                    </h1>
                    <p className="mt-2 text-sm text-zinc-400">
                        Administrasi tenant akan tersedia di fase berikutnya.
                    </p>
                </div>
            </main>
        </div>
    );
}
