import { Head, Link } from '@inertiajs/react';

import TenantShell from '@shared/components/TenantShell';
import { Button } from '@shared/components/ui/button';

import { invite as usersInvite } from '@/actions/Modules/Identity/App/Http/Controllers/UsersManagementController';

type Accounts = {
    total: number;
    active: number;
    awaitingActivation: number;
    deactivated: number;
    withoutRole: number;
};

/** The student or teacher record behind the signed-in account. */
type Me =
    | { kind: 'student'; name: string; nis: string; class: string | null }
    | { kind: 'teacher'; name: string; duty: string };

interface BerandaProps {
    school: { name: string; slug: string; timezone: string };
    today: { label: string; iso: string };
    me: Me | null;
    /** Only for those who manage users; null for everyone else. */
    accounts: Accounts | null;
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
 * The line for someone who does not manage accounts: who they are here.
 */
function personLine(me: Me | null): DayLine {
    if (me?.kind === 'student') {
        return {
            lead: 'Anda masuk sebagai',
            word: me.name,
            tail: me.class !== null ? `, siswa kelas ${me.class}.` : ', siswa.',
            confirmed: true,
        };
    }

    if (me?.kind === 'teacher') {
        return {
            lead: 'Anda masuk sebagai',
            word: me.name,
            tail: `, ${me.duty}.`,
            confirmed: true,
        };
    }

    return {
        lead: 'Anda sudah',
        word: 'masuk',
        tail: ' ke portal sekolah.',
        confirmed: true,
    };
}

/**
 * Beranda Sekolah — today's page of the school's own record, dated on
 * the school's own clock, inside the tenant shell.
 */
export default function Beranda({
    school,
    today,
    me,
    accounts,
    roles,
    can,
}: BerandaProps) {
    const line = accounts === null ? personLine(me) : dayLine(accounts);

    const accountSummary =
        accounts === null
            ? null
            : [
                  `${accounts.total} total`,
                  `${accounts.active} aktif`,
                  ...(accounts.awaitingActivation > 0
                      ? [`${accounts.awaitingActivation} menunggu`]
                      : []),
              ].join(' · ');

    return (
        <TenantShell width="max-w-xl">
            <Head title={school.name} />

            <div>
                {/* The sheet header: the school, and the day on its own
                    clock. One rule underneath holds the two together. */}
                <div className="sm:flex sm:items-baseline sm:justify-between sm:gap-8">
                    <h1 className="text-xl leading-7 font-semibold text-balance text-foreground">
                        {school.name}
                    </h1>

                    <div className="mt-2 sm:mt-0 sm:shrink-0 sm:text-right">
                        <p className="text-xl leading-7 font-medium whitespace-nowrap text-muted-foreground">
                            <time dateTime={today.iso}>{today.label}</time>
                        </p>

                        <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                            {school.timezone}
                        </p>
                    </div>
                </div>

                <div className="day-rule mt-8 border-t border-border pt-5">
                    <p className="text-sm leading-6 text-balance text-foreground">
                        {line.lead}{' '}
                        <span
                            className={
                                line.confirmed
                                    ? 'font-semibold text-primary'
                                    : 'font-semibold text-foreground'
                            }
                        >
                            {line.word}
                        </span>
                        {line.tail}
                    </p>

                    {can.invite && (
                        <Button asChild className="mt-5 w-full">
                            <Link href={usersInvite.url()}>Undang staf</Link>
                        </Button>
                    )}
                </div>

                {/* The rest of the record: quiet, ruled, 12px. Numbers
                    never replace the day's sentence; they only back it. */}
                <dl className="mt-10 border-t border-border">
                    <div className="flex items-baseline justify-between gap-6 border-b border-border py-3">
                        <dt className="text-xs text-muted-foreground">
                            Kode sekolah
                        </dt>
                        <dd className="font-mono text-xs text-foreground">
                            {school.slug}
                        </dd>
                    </div>

                    {me?.kind === 'student' && (
                        <div className="flex items-baseline justify-between gap-6 border-b border-border py-3">
                            <dt className="text-xs text-muted-foreground">
                                NIS
                            </dt>
                            <dd className="font-mono text-xs text-foreground">
                                {me.nis}
                            </dd>
                        </div>
                    )}

                    {accountSummary !== null && (
                        <>
                            <div className="flex items-baseline justify-between gap-6 border-b border-border py-3">
                                <dt className="text-xs text-muted-foreground">
                                    Peran
                                </dt>
                                <dd className="text-right text-xs text-foreground">
                                    {roles.join(' · ')}
                                </dd>
                            </div>

                            <div className="flex items-baseline justify-between gap-6 border-b border-border py-3">
                                <dt className="text-xs text-muted-foreground">
                                    Akun
                                </dt>
                                <dd className="text-right text-xs text-foreground">
                                    {accountSummary}
                                </dd>
                            </div>
                        </>
                    )}

                    {accounts !== null && accounts.deactivated > 0 && (
                        <div className="flex items-baseline justify-between gap-6 border-b border-border py-3">
                            <dt className="text-xs text-muted-foreground">
                                Dinonaktifkan
                            </dt>
                            <dd className="text-right text-xs text-foreground">
                                {accounts.deactivated} · tetap tersimpan
                            </dd>
                        </div>
                    )}
                </dl>
            </div>
        </TenantShell>
    );
}
