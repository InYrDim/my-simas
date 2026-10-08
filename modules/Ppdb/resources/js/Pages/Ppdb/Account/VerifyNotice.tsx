import { router } from '@inertiajs/react';
import { useState } from 'react';

import { destroy as logout } from '@/actions/Modules/Ppdb/App/Http/Controllers/Account/SessionController';
import { resend } from '@/actions/Modules/Ppdb/App/Http/Controllers/Account/VerificationController';
import { LembarButton } from '@shared/components/lembar/fields';
import { LembarShell } from '@shared/components/lembar/LembarShell';

/**
 * Shown until the applicant opens the link in their verification mail. The
 * rest of the account stays closed while the email is unverified.
 */
export default function VerifyNotice({ email }: { email: string }) {
    const [sending, setSending] = useState(false);

    return (
        <LembarShell
            area="PPDB · Calon siswa"
            sheet="Lembar verifikasi email"
            title="Verifikasi email Anda"
            lead={`Kami mengirim tautan verifikasi ke ${email}. Buka tautan itu untuk melanjutkan.`}
            progress={1 / 3}
            instructions={[
                'Buat akun dan buka tautan verifikasi di email.',
                'Bergabung ke sekolah dengan kode atau tautan dari sekolah.',
                'Isi formulir pendaftaran, lalu pantau statusnya di halaman akun.',
            ]}
        >
            <p className="text-[15px] leading-relaxed">
                Email belum sampai? Periksa folder spam, atau kirim ulang
                tautannya.
            </p>
            <div className="flex flex-col gap-3 sm:flex-row">
                <LembarButton
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
                </LembarButton>
                <LembarButton quiet onClick={() => router.post(logout.url())}>
                    Keluar
                </LembarButton>
            </div>
        </LembarShell>
    );
}
