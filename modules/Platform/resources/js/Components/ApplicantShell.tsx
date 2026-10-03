import { Head, usePage } from '@inertiajs/react';
import { CheckIcon, GraduationCapIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { cn } from '@shared/lib/utils';

/**
 * The registration steps, in order. A page names its own step (1-based);
 * pages outside the registration flow (masuk, lupa kata sandi) pass none.
 */
const steps = [
    { title: 'Buat akun', hint: 'Nama, email, dan kata sandi Anda' },
    { title: 'Verifikasi email', hint: 'Buka tautan yang kami kirim' },
    {
        title: 'Data sekolah & paket',
        hint: 'Isi data sekolah, pilih paket trial',
    },
    { title: 'Persetujuan', hint: 'Tim kami meninjau, lalu sekolah aktif' },
];

function Brand({ className }: { className?: string }) {
    return (
        <div className={cn('flex items-center gap-2 font-semibold', className)}>
            <span className="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                <GraduationCapIcon className="size-4" />
            </span>
            SIMAS
        </div>
    );
}

/** Desktop side panel: what SIMAS is and, in the flow, where the applicant is. */
function SidePanel({ step }: { step?: number }) {
    return (
        <aside className="hidden flex-col justify-between bg-primary p-10 text-primary-foreground lg:flex xl:p-14">
            <div className="flex items-center gap-2 text-lg font-semibold">
                <span className="flex size-9 items-center justify-center rounded-md bg-primary-foreground/15">
                    <GraduationCapIcon className="size-5" />
                </span>
                SIMAS
            </div>

            <div className="flex flex-col gap-8">
                <div className="flex flex-col gap-2">
                    <h2 className="text-3xl leading-tight font-semibold text-balance">
                        Satu sistem untuk seluruh urusan sekolah Anda.
                    </h2>
                    <p className="text-primary-foreground/80">
                        Daftarkan sekolah, mulai trial tanpa pembayaran, dan tim
                        kami membantu sampai sekolah Anda aktif.
                    </p>
                </div>

                {step !== undefined && (
                    <ol className="flex flex-col gap-5">
                        {steps.map((item, index) => {
                            const number = index + 1;
                            const done = number < step;
                            const current = number === step;

                            return (
                                <li
                                    key={item.title}
                                    aria-current={current ? 'step' : undefined}
                                    className={cn(
                                        'flex items-start gap-3',
                                        !done && !current && 'opacity-60',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex size-7 shrink-0 items-center justify-center rounded-full border text-sm font-medium',
                                            current
                                                ? 'border-primary-foreground bg-primary-foreground text-primary'
                                                : 'border-primary-foreground/60',
                                        )}
                                    >
                                        {done ? (
                                            <CheckIcon className="size-4" />
                                        ) : (
                                            number
                                        )}
                                    </span>
                                    <span className="flex flex-col">
                                        <span className="font-medium">
                                            {item.title}
                                        </span>
                                        <span className="text-sm text-primary-foreground/80">
                                            {item.hint}
                                        </span>
                                    </span>
                                </li>
                            );
                        })}
                    </ol>
                )}
            </div>

            <p className="text-sm text-primary-foreground/70">
                Butuh bantuan? Hubungi tim SIMAS lewat email yang Anda terima.
            </p>
        </aside>
    );
}

/** Mobile header: the brand and a compact progress bar for the flow. */
function MobileHeader({ step }: { step?: number }) {
    return (
        <header className="flex flex-col gap-4 lg:hidden">
            <Brand />

            {step !== undefined && (
                <div className="flex flex-col gap-2">
                    <div className="flex gap-1.5" aria-hidden>
                        {steps.map((item, index) => (
                            <span
                                key={item.title}
                                className={cn(
                                    'h-1 flex-1 rounded-full',
                                    index + 1 <= step
                                        ? 'bg-primary'
                                        : 'bg-muted',
                                )}
                            />
                        ))}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Langkah {step} dari {steps.length} ·{' '}
                        <span className="font-medium text-foreground">
                            {steps[step - 1]?.title}
                        </span>
                    </p>
                </div>
            )}
        </header>
    );
}

/**
 * Frame for the applicant pages (daftar, masuk, verifikasi, onboarding).
 * Desktop: a two-column screen, side panel left and the page right.
 * Mobile: one column, brand and progress on top, the page full width with
 * no card around it. No navigation — each page points at one action.
 */
export default function ApplicantShell({
    title,
    description,
    width = 'max-w-md',
    step,
    footer,
    children,
}: {
    title: string;
    description?: string;
    width?: string;
    step?: number;
    footer?: ReactNode;
    children: ReactNode;
}) {
    const { flash } = usePage<{ flash?: { status?: string | null } }>().props;

    return (
        <div className="grid min-h-[100dvh] bg-background text-foreground lg:grid-cols-[minmax(22rem,2fr)_3fr]">
            <Head title={title} />

            <SidePanel step={step} />

            <main className="flex flex-col gap-8 px-4 py-6 sm:px-8 sm:py-10 lg:items-center lg:justify-center lg:px-12 lg:py-14">
                <div className={cn('flex w-full flex-col gap-8', width)}>
                    <MobileHeader step={step} />

                    <div className="flex flex-col gap-6">
                        {flash?.status && (
                            <Alert>
                                <AlertDescription>
                                    {flash.status}
                                </AlertDescription>
                            </Alert>
                        )}

                        <div className="flex flex-col gap-1.5">
                            <h1 className="text-2xl font-semibold tracking-tight text-balance">
                                {title}
                            </h1>
                            {description !== undefined && (
                                <p className="text-sm text-muted-foreground">
                                    {description}
                                </p>
                            )}
                        </div>

                        {children}

                        {footer !== undefined && (
                            <div className="border-t pt-4 text-sm text-muted-foreground">
                                {footer}
                            </div>
                        )}
                    </div>
                </div>
            </main>
        </div>
    );
}
