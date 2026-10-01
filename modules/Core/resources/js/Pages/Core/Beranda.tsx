import { Head, Link } from '@inertiajs/react';

import {
    index as usersIndex,
    invite as usersInvite,
} from '@/actions/Modules/Identity/App/Http/Controllers/UsersManagementController';

type Accounts = {
    total: number;
    active: number;
    awaitingActivation: number;
    deactivated: number;
    withoutRole: number;
};

interface BerandaProps {
    school: { name: string; slug: string; timezone: string };
    today: { label: string; iso: string };
    accounts: Accounts;
    roles: string[];
    can: { viewUsers: boolean; invite: boolean };
}

/** One sentence, one emphasised phrase — the single thing waiting here. */
interface DayLine {
    lead: string;
    word: string;
    tail: string;
    confirmed: boolean;
}

/**
 * The day's honest line, in the order the work actually waits: a role
 * the admin still owes, then an invitation the colleague has not
 * answered, then the truth of a school with nobody else in it, then the
 * settled day.
 */
function dayLine(accounts: Accounts): DayLine {
    if (accounts.withoutRole > 0) {
        return {
            lead: `${accounts.withoutRole} akun`,
            word: 'belum punya peran',
            tail: ' di sekolah ini.',
            confirmed: false,
        };
    }

    if (accounts.awaitingActivation > 0) {
        return {
            lead: `${accounts.awaitingActivation} akun`,
            word: 'menunggu aktifasi',
            tail: ' dari undangan.',
            confirmed: false,
        };
    }

    if (accounts.total <= 1) {
        return {
            lead: 'Hanya',
            word: 'akun Anda',
            tail: ' yang aktif di sekolah ini.',
            confirmed: false,
        };
    }

    return {
        lead: 'Semua akun aktif',
        word: 'sudah punya peran',
        tail: '.',
        confirmed: true,
    };
}

/**
 * Beranda Sekolah — today's page of the school's own record, dated on
 * the school's own clock. No icons, no cards, no shadows: the sheet is
 * held by its rules, and the only green on the page means confirmed or
 * go.
 */
export default function Beranda({
    school,
    today,
    accounts,
    roles,
    can,
}: BerandaProps) {
    const line = dayLine(accounts);

    const accountSummary = [
        `${accounts.total} total`,
        `${accounts.active} aktif`,
        ...(accounts.awaitingActivation > 0
            ? [`${accounts.awaitingActivation} menunggu`]
            : []),
    ].join(' · ');

    return (
        <div className="beranda min-h-[100dvh] bg-zinc-50 text-zinc-900">
            <Head title={school.name} />

            <header className="border-b border-zinc-200 bg-white">
                <div className="mx-auto flex h-16 max-w-xl items-center justify-between px-6">
                    <span className="text-sm font-semibold text-zinc-900">
                        Beranda
                    </span>

                    {can.viewUsers && (
                        <Link
                            href={usersIndex.url()}
                            className="-mr-1 rounded px-1 py-2 text-sm text-zinc-500 transition-colors hover:text-zinc-900"
                        >
                            Pengguna
                        </Link>
                    )}
                </div>
            </header>

            <main className="mx-auto max-w-xl px-6 py-8 sm:py-10">
                {/* The sheet header: the school, and the day on its own
                    clock. One rule underneath holds the two together. */}
                <div className="sm:flex sm:items-baseline sm:justify-between sm:gap-8">
                    <h1 className="text-xl leading-7 font-semibold text-balance text-zinc-900">
                        {school.name}
                    </h1>

                    <div className="mt-2 sm:mt-0 sm:shrink-0 sm:text-right">
                        <p className="text-xl leading-7 font-medium whitespace-nowrap text-zinc-500">
                            <time dateTime={today.iso}>{today.label}</time>
                        </p>

                        <p className="mt-0.5 font-mono text-xs text-zinc-400">
                            {school.timezone}
                        </p>
                    </div>
                </div>

                <div className="day-rule mt-8 border-t border-zinc-200 pt-5">
                    <p className="text-sm leading-6 text-balance text-zinc-900">
                        {line.lead}{' '}
                        <span
                            className={
                                line.confirmed
                                    ? 'font-semibold text-emerald-700'
                                    : 'font-semibold text-zinc-900'
                            }
                        >
                            {line.word}
                        </span>
                        {line.tail}
                    </p>

                    {can.invite && (
                        <Link
                            href={usersInvite.url()}
                            className="mt-5 flex min-h-11 w-full items-center justify-center rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-800 active:translate-y-px"
                        >
                            Undang staf
                        </Link>
                    )}
                </div>

                {/* The rest of the record: quiet, ruled, 12px. Numbers
                    never replace the day's sentence; they only back it. */}
                <dl className="mt-10 border-t border-zinc-200">
                    <div className="flex items-baseline justify-between gap-6 border-b border-zinc-200 py-3">
                        <dt className="text-xs text-zinc-500">Kode sekolah</dt>
                        <dd className="font-mono text-xs text-zinc-900">
                            {school.slug}
                        </dd>
                    </div>

                    <div className="flex items-baseline justify-between gap-6 border-b border-zinc-200 py-3">
                        <dt className="text-xs text-zinc-500">Peran</dt>
                        <dd className="text-right text-xs text-zinc-900">
                            {roles.join(' · ')}
                        </dd>
                    </div>

                    <div className="flex items-baseline justify-between gap-6 border-b border-zinc-200 py-3">
                        <dt className="text-xs text-zinc-500">Akun</dt>
                        <dd className="text-right text-xs text-zinc-900">
                            {accountSummary}
                        </dd>
                    </div>

                    {accounts.deactivated > 0 && (
                        <div className="flex items-baseline justify-between gap-6 border-b border-zinc-200 py-3">
                            <dt className="text-xs text-zinc-500">
                                Dinonaktifkan
                            </dt>
                            <dd className="text-right text-xs text-zinc-900">
                                {accounts.deactivated} · tetap tersimpan
                            </dd>
                        </div>
                    )}
                </dl>
            </main>
        </div>
    );
}
