import { Head, Link } from '@inertiajs/react';

import { show as showApplication } from '@/actions/Modules/Platform/App/Http/Controllers/ApplicationReviewController';
import type { ApplicationData } from '../../../types/ApplicationData';

import { consolePath } from '../../../Components/consolePath';
import {
    EmptyState,
    PageHeader,
    StatusChip,
} from '../../../Components/ConsoleParts';
import ProviderLayout from '../../../Components/ProviderLayout';

interface IndexProps {
    applications: ApplicationData[];
}

/**
 * Provider console: pending school applications (oldest first). The
 * working list is pending only; decided rows are reachable via their
 * detail page for situational awareness.
 */
export default function ApplicationsIndex({ applications }: IndexProps) {
    return (
        <ProviderLayout width="max-w-4xl">
            <Head title="Pengajuan Sekolah" />

            <PageHeader
                title="Pengajuan Sekolah"
                description="Tinjau pengajuan, koreksi data bila perlu, lalu ACC atau tolak."
            />

            <div className="mt-8">
                {applications.length === 0 ? (
                    <EmptyState>
                        Tidak ada pengajuan pending. Semua beres.
                    </EmptyState>
                ) : (
                    <ul className="flex flex-col gap-3">
                        {applications.map((application) => (
                            <li key={application.id}>
                                <Link
                                    href={consolePath(
                                        showApplication.url({
                                            application: application.id,
                                        }),
                                    )}
                                    className="block bg-card px-5 py-4 shadow-sm transition-colors hover:bg-muted"
                                >
                                    <div className="flex items-center justify-between gap-4">
                                        <div>
                                            <p className="font-medium">
                                                {application.schoolName}
                                            </p>
                                            <p className="mt-0.5 text-sm text-muted-foreground">
                                                {application.applicantName} ·{' '}
                                                {application.applicantEmail}
                                            </p>
                                        </div>

                                        <div className="flex flex-col items-end gap-1">
                                            <StatusChip status="pending" />
                                            <p className="font-mono text-xs text-muted-foreground">
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
