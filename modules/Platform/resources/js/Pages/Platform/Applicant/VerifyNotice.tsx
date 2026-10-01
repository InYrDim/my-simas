import { router } from '@inertiajs/react';
import { useState } from 'react';

import { destroy as logout } from '@/actions/Modules/Platform/App/Http/Controllers/Applicant/SessionController';
import { resend } from '@/actions/Modules/Platform/App/Http/Controllers/Applicant/VerificationController';
import { Button } from '@shared/components/ui/button';

import ApplicantShell from '../../../Components/ApplicantShell';

/**
 * Shown until the applicant opens the link in their verification mail.
 * Onboarding stays closed while the email is unverified.
 */
export default function VerifyNotice({ email }: { email: string }) {
    const [sending, setSending] = useState(false);

    return (
        <ApplicantShell
            title="Verifikasi email Anda"
            description={`Kami mengirim tautan verifikasi ke ${email}. Buka tautan itu untuk melanjutkan pendaftaran sekolah.`}
        >
            <div className="flex flex-col gap-3">
                <Button
                    disabled={sending}
                    onClick={() =>
                        router.post(
                            resend.url(),
                            {},
                            {
                                preserveScroll: true,
                                onStart: () => setSending(true),
                                onFinish: () => setSending(false),
                            },
                        )
                    }
                >
                    {sending ? 'Mengirim...' : 'Kirim ulang tautan'}
                </Button>
                <Button variant="outline" onClick={() => router.post(logout.url())}>
                    Keluar
                </Button>
            </div>
        </ApplicantShell>
    );
}
