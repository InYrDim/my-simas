import { router } from '@inertiajs/react';
import { useState } from 'react';

import { destroy as logout } from '@/actions/Modules/Ppdb/App/Http/Controllers/Account/SessionController';
import { resend } from '@/actions/Modules/Ppdb/App/Http/Controllers/Account/VerificationController';
import { Button } from '@shared/components/ui/button';

import PortalPage from '../../../Components/PortalPage';

/**
 * Shown until the applicant opens the link in their verification mail. The
 * rest of the account stays closed while the email is unverified.
 */
export default function VerifyNotice({ email }: { email: string }) {
    const [sending, setSending] = useState(false);

    return (
        <PortalPage
            title="Verifikasi email Anda"
            description={`Kami mengirim tautan verifikasi ke ${email}. Buka tautan itu untuk melanjutkan.`}
        >
            <div className="flex flex-col gap-3 sm:flex-row">
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
        </PortalPage>
    );
}
