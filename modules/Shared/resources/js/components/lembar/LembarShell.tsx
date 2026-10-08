import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { LembarNotice } from './fields';
import { CornerMarks, TimingRail, Wordmark } from './parts';

/**
 * The frame of a sign-in or account page in the Lembar world: the timing
 * rail, a masthead, the page title with its instructions, and one framed
 * sheet holding the form. Used by the school's sign-in pages and by the
 * PPDB applicant's account pages; it knows nothing about either.
 *
 * At `lg` the title and instructions sit left of the sheet; on a phone
 * the order is title, sheet, then instructions, so the form comes first.
 */
export function LembarShell({
    area,
    sheet,
    sheetNote,
    title,
    lead,
    instructions = [],
    progress = 0,
    after,
    children,
}: {
    /** Which door this is, printed in caps in the masthead. */
    area: string;
    /** The sheet's printed name in its header strip. */
    sheet: string;
    /** Right side of the header strip, e.g. the school's name. */
    sheetNote?: ReactNode;
    title: string;
    lead?: ReactNode;
    /** "Petunjuk pengisian": short, true lines; numbered in print. */
    instructions?: ReactNode[];
    /** How much of the form is filled in (0..1), shown on the rail. */
    progress?: number;
    /** Links under the sheet, e.g. to make an account. */
    after?: ReactNode;
    children: ReactNode;
}) {
    const { flash } = usePage<{ flash?: { status?: string | null } }>().props;

    return (
        <div className="lembar min-h-dvh">
            <Head title={title} />

            <TimingRail progress={progress} />

            <div className="flex min-h-dvh flex-col pl-7 sm:pl-12">
                <header className="border-b-2 border-(--ink)">
                    <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-5 sm:px-8">
                        <Link
                            href="/"
                            className="inline-flex min-h-11 items-center"
                        >
                            <Wordmark />
                        </Link>
                        <span className="text-right text-xs font-semibold tracking-[0.12em] text-(--ink-deep) uppercase">
                            {area}
                        </span>
                    </div>
                </header>

                <main className="mx-auto grid w-full max-w-6xl flex-1 content-start gap-x-12 gap-y-8 px-5 pt-8 pb-14 sm:px-8 sm:pt-14 lg:grid-cols-12 lg:pt-20">
                    <div className="lg:col-span-5 lg:pt-2">
                        <h1 className="text-[2.25rem] leading-[0.95] font-extrabold tracking-[-0.02em] text-balance [font-stretch:75%] sm:text-[3rem] lg:text-[3.5rem]">
                            {title}
                        </h1>
                        {lead && (
                            <p className="mt-4 max-w-[30rem] text-base leading-relaxed text-pretty text-(--pencil) sm:mt-5 sm:text-lg">
                                {lead}
                            </p>
                        )}
                        {instructions.length > 0 && (
                            <Instructions
                                id="petunjuk"
                                lines={instructions}
                                className="mt-12 hidden lg:block"
                            />
                        )}
                    </div>

                    <div className="lg:col-span-6 lg:col-start-7">
                        <section
                            aria-label={sheet}
                            className="relative border-2 border-(--ink) bg-white px-5 pt-5 pb-6 sm:px-8 sm:pt-8 sm:pb-8"
                        >
                            <CornerMarks />

                            <div className="flex flex-col items-start gap-1 border border-(--ink) bg-(--ink-tint) px-4 py-2.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                                <span className="text-sm font-bold tracking-[0.08em] text-(--ink-deep) uppercase [font-stretch:85%]">
                                    {sheet}
                                </span>
                                {sheetNote && (
                                    <span className="text-sm font-semibold text-(--graphite) sm:text-right">
                                        {sheetNote}
                                    </span>
                                )}
                            </div>

                            <div className="mt-6 flex flex-col gap-6">
                                {flash?.status && (
                                    <LembarNotice>{flash.status}</LembarNotice>
                                )}
                                {children}
                            </div>
                        </section>

                        {after && (
                            <div className="mt-5 flex flex-col gap-x-6 text-[15px] text-(--pencil) sm:flex-row sm:flex-wrap sm:items-center">
                                {after}
                            </div>
                        )}
                    </div>

                    {instructions.length > 0 && (
                        <Instructions
                            id="petunjuk-hp"
                            lines={instructions}
                            className="lg:hidden"
                        />
                    )}
                </main>

                <footer className="mx-auto w-full max-w-6xl px-5 py-6 text-sm text-(--pencil) sm:px-8">
                    <Wordmark className="text-sm text-(--graphite)" /> · Sistem
                    Informasi Manajemen Sekolah
                </footer>
            </div>
        </div>
    );
}

function Instructions({
    id,
    lines,
    className,
}: {
    id: string;
    lines: ReactNode[];
    className: string;
}) {
    return (
        <section
            aria-labelledby={id}
            className={`border-t-2 border-(--ink) pt-4 ${className}`}
        >
            <h2
                id={id}
                className="text-xs font-bold tracking-[0.1em] text-(--ink-deep) uppercase"
            >
                Petunjuk pengisian
            </h2>
            <ol className="mt-2">
                {lines.map((line, index) => (
                    <li
                        key={index}
                        className="grid max-w-[30rem] grid-cols-[1.75rem_1fr] border-b border-(--ink-line) py-3 text-[15px] leading-relaxed text-(--pencil) last:border-b-0"
                    >
                        <span className="font-code text-(--ink-deep)">
                            {index + 1}.
                        </span>
                        <span>{line}</span>
                    </li>
                ))}
            </ol>
        </section>
    );
}

/** Share of the given answers that are filled in, for the timing rail. */
export function filledShare(values: Array<string | boolean>): number {
    if (values.length === 0) {
        return 0;
    }

    const filled = values.filter((value) =>
        typeof value === 'string' ? value.trim() !== '' : value,
    ).length;

    return filled / values.length;
}
