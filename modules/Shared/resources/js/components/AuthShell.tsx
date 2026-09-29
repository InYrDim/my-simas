import type { ReactNode } from 'react';

/**
 * Shared shell for both auth surfaces. One design language, two tones:
 *
 * - "light"  : school portal (tenant staff), calm and institutional
 * - "dark"   : provider console (SaaS staff), operator-grade
 *
 * Auth pages are intentionally free of navigation: a login screen
 * points to exactly one action.
 */
export type AuthShellProps = {
    tone: 'light' | 'dark';
    /** Small caption above the title, e.g. the school name. */
    eyebrow?: string;
    title: string;
    subtitle?: string;
    children: ReactNode;
    /** Footer line, e.g. product name + host hint. */
    footer?: string;
};

export function AuthShell({ tone, eyebrow, title, subtitle, children, footer }: AuthShellProps) {
    const isDark = tone === 'dark';

    return (
        <div
            className={`flex min-h-[100dvh] flex-col items-center justify-center px-4 py-10 ${
                isDark ? 'bg-zinc-950' : 'bg-zinc-100'
            }`}
        >
            <div className="w-full max-w-sm">
                <div
                    className={`rounded-xl border px-8 py-8 ${
                        isDark
                            ? 'border-zinc-800 bg-zinc-900 shadow-[0_1px_0_rgba(255,255,255,0.04)_inset]'
                            : 'border-zinc-200 bg-white shadow-sm'
                    }`}
                >
                    {eyebrow ? (
                        <p
                            className={`mb-1 text-sm ${
                                isDark ? 'text-zinc-400' : 'text-zinc-500'
                            }`}
                        >
                            {eyebrow}
                        </p>
                    ) : null}

                    <h1
                        className={`text-xl leading-7 font-semibold ${
                            isDark ? 'text-zinc-100' : 'text-zinc-900'
                        }`}
                    >
                        {title}
                    </h1>

                    {subtitle ? (
                        <p
                            className={`mt-1 text-sm leading-5 ${
                                isDark ? 'text-zinc-400' : 'text-zinc-500'
                            }`}
                        >
                            {subtitle}
                        </p>
                    ) : null}

                    <div className="mt-6">{children}</div>
                </div>

                {footer ? (
                    <p
                        className={`mt-4 text-center text-xs ${
                            isDark ? 'text-zinc-500' : 'text-zinc-400'
                        }`}
                    >
                        {footer}
                    </p>
                ) : null}
            </div>
        </div>
    );
}
