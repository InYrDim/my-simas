import { Head, Link } from '@inertiajs/react';
import { consolePath } from '../../../Components/consolePath';

import ProviderLayout from '../../../Components/ProviderLayout';

import { show as showApplication } from '@/actions/Modules/Platform/App/Http/Controllers/ApplicationReviewController';

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
        <ProviderLayout width="max-w-4xl">
            <Head title="Pengajuan Sekolah" />

            <div>
                <h1 className="text-xl font-semibold text-foreground">
                    Pengajuan Sekolah
                </h1>

                <p className="mt-1 text-sm text-muted-foreground">
                    Tinjau pengajuan, koreksi data bila perlu, lalu ACC atau
                    tolak.
                </p>

                {status !== undefined && status !== '' && (
                    <div className="mt-6 rounded-lg border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-foreground">
                        {status}
                    </div>
                )}

                {applications.length === 0 ? (
                    <div className="mt-10 rounded-lg border border-border bg-card px-6 py-10 text-center">
                        <p className="text-sm text-muted-foreground">
                            Tidak ada pengajuan pending. Semua beres.
                        </p>
                    </div>
                ) : (
                    <ul className="mt-8 flex flex-col gap-3">
                        {applications.map((application) => (
                            <li key={application.id}>
                                <Link
                                    href={consolePath(showApplication.url({
                                        application: application.id,
                                    }))}
                                    className="block rounded-lg border border-border bg-card px-5 py-4 transition-colors hover:border-primary/40 hover:bg-muted"
                                >
                                    <div className="flex items-center justify-between gap-4">
                                        <div>
                                            <p className="font-medium text-foreground">
                                                {application.schoolName}
                                            </p>
                                            <p className="mt-0.5 text-sm text-muted-foreground">
                                                {application.applicantName} ·{' '}
                                                {application.applicantEmail}
                                            </p>
                                        </div>

                                        <div className="text-right">
                                            <span className="rounded-md border border-accent/40 bg-accent/10 px-2.5 py-0.5 text-xs font-medium text-foreground">
                                                pending
                                            </span>
                                            <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                /{application.desiredSlug}
                                            </p>
                                        </div>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </ProviderLayout>
    );
}
