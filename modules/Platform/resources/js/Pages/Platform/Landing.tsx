import { Head, Link } from '@inertiajs/react';
import { ArrowRightIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';

import {
    links,
    modules,
    principles,
    roles,
    roleTasks,
    steps,
} from '../../Components/Landing/content';
import {
    Bubble,
    CornerMarks,
    TimingRail,
    Wordmark,
} from '@shared/components/lembar/parts';

const SCHOOL_CODE = '123456';
const LEVELS = ['SD', 'SMP', 'SMA', 'SMK'];
const STATUSES = ['Diajukan', 'Ditinjau tim kami', 'Disetujui'];
/** The sample sheet stops mid-review: approval is a person's step. */
const STATUSES_DONE = 2;

/**
 * The public landing page ("Lembar"), shown at / to a visitor with no
 * session. The page is an answer sheet every Indonesian school knows by
 * heart: blue drop-out ink rules on white paper, black timing marks down
 * the edge, and bubbles filled in 2B pencil. Filled once, correctly — the
 * job of the teacher this product is built for.
 */
export default function Landing({ trialDays }: { trialDays: number }) {
    return (
        <div className="lembar min-h-dvh">
            <Head title="Sistem Informasi Manajemen Sekolah">
                <meta
                    name="description"
                    content="SIMAS: ruang kerja sendiri untuk setiap sekolah. Data induk, jadwal, absensi, PPDB, dan WhatsApp sekolah."
                />
            </Head>

            <TimingRail />

            <div className="pl-7 sm:pl-12">
                <Masthead />
                <main>
                    <Hero trialDays={trialDays} />
                    <ModulesSection />
                    <FlowSection />
                    <RolesSection />
                    <RulesSection />
                    <CloseSection />
                </main>
                <Footer />
            </div>
        </div>
    );
}

function PrimaryButton({
    href,
    children,
    inverted = false,
}: {
    href: string;
    children: ReactNode;
    inverted?: boolean;
}) {
    return (
        <Link
            href={href}
            className={`inline-flex h-12 items-center justify-center gap-2 rounded-[2px] px-6 text-[15px] font-semibold transition-colors active:translate-y-px ${
                inverted
                    ? 'bg-white text-(--graphite) hover:bg-(--ink-tint)'
                    : 'bg-(--graphite) text-white hover:bg-(--ink-deep)'
            }`}
        >
            {children}
        </Link>
    );
}

function Masthead() {
    return (
        <header className="border-b-2 border-(--ink)">
            <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-5 sm:h-20 sm:px-8">
                <div className="flex items-baseline gap-4">
                    <Wordmark />
                    <span className="hidden text-xs font-semibold tracking-[0.12em] text-(--ink-deep) uppercase md:inline">
                        Sistem Informasi Manajemen Sekolah
                    </span>
                </div>
                <nav
                    aria-label="Utama"
                    className="flex items-center gap-1 sm:gap-2"
                >
                    <Link
                        href={links.ppdbRegister}
                        className="inline-flex h-11 items-center px-2 text-[15px] font-medium text-(--pencil) transition-colors hover:text-(--graphite) sm:px-3"
                    >
                        Calon siswa
                    </Link>
                    <Link
                        href={links.schoolLogin}
                        className="inline-flex h-11 items-center px-2 text-[15px] font-medium text-(--pencil) transition-colors hover:text-(--graphite) sm:px-3"
                    >
                        Masuk
                    </Link>
                    <span className="hidden sm:inline-flex">
                        <PrimaryButton href={links.register}>
                            Daftarkan sekolah
                        </PrimaryButton>
                    </span>
                </nav>
            </div>
        </header>
    );
}

function Hero({ trialDays }: { trialDays: number }) {
    return (
        <section className="mx-auto grid max-w-7xl gap-12 px-5 pt-12 pb-20 sm:px-8 sm:pt-20 lg:grid-cols-12 lg:gap-10 lg:pb-28">
            <div className="lg:col-span-6 lg:pt-6">
                <h1 className="text-[2.75rem] leading-[0.95] font-extrabold tracking-[-0.025em] text-balance [font-stretch:72%] sm:text-[4.25rem] xl:text-[5.25rem]">
                    Administrasi sekolah, diisi sekali dan benar.
                </h1>
                <p className="mt-7 max-w-[34rem] text-lg leading-relaxed text-(--pencil)">
                    SIMAS adalah ruang kerja sendiri untuk setiap sekolah: data
                    induk, jadwal, absensi, PPDB, dan nomor WhatsApp sekolah.
                    Guru mencatat dari HP, kantor mengelola dari komputer.
                </p>
                <div className="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-6">
                    <PrimaryButton href={links.register}>
                        Daftarkan sekolah
                    </PrimaryButton>
                    <Link
                        href={links.schoolLogin}
                        className="group inline-flex h-12 items-center gap-2 text-[15px] font-semibold underline decoration-(--ink) decoration-2 underline-offset-[6px] transition-colors hover:text-(--ink-deep)"
                    >
                        Masuk ke sekolah Anda
                        <ArrowRightIcon
                            aria-hidden
                            className="size-4 transition-transform group-hover:translate-x-0.5"
                        />
                    </Link>
                </div>
                <p className="mt-8 max-w-[34rem] border-t border-(--ink-line) pt-4 text-sm text-(--pencil)">
                    Trial {trialDays} hari, dimulai saat pengajuan disetujui.
                    Tanpa pembayaran di awal.
                </p>
            </div>

            <div className="lg:col-span-6">
                <ApplicationSheet />
            </div>
        </section>
    );
}

/**
 * The hero's demonstration: an application sheet filling itself in, then
 * the step this product is built around — a person reviews it.
 */
function ApplicationSheet() {
    const [enabled, setEnabled] = useState<Set<string>>(
        () => new Set(['absensi', 'ppdb', 'induk']),
    );
    // Once the visitor marks a bubble, it fills at once: the staggered
    // delays belong to the page-load moment only.
    const [touched, setTouched] = useState(false);

    function toggle(key: string) {
        setTouched(true);
        setEnabled((current) => {
            const next = new Set(current);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });
    }

    const codeDelay = (column: number) => 200 + column * 140;
    const levelDelay = codeDelay(SCHOOL_CODE.length) + 120;
    const moduleDelay = (index: number) => levelDelay + 200 + index * 110;
    const statusDelay = (index: number) =>
        moduleDelay(modules.length) + 260 + index * 380;

    return (
        <figure className="relative border-2 border-(--ink) bg-white px-5 pt-5 pb-6 sm:px-8 sm:pt-8 sm:pb-8">
            <CornerMarks />

            <div className="flex flex-col items-start gap-1 border border-(--ink) bg-(--ink-tint) px-4 py-2.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                <span className="text-sm font-bold tracking-[0.08em] text-(--ink-deep) uppercase [font-stretch:85%]">
                    Lembar pengajuan sekolah
                </span>
                <span className="text-xs font-semibold text-(--ink-deep)">
                    Contoh pengisian
                </span>
            </div>

            <div className="mt-6 grid gap-8 sm:grid-cols-[auto_1fr] sm:gap-10">
                <fieldset>
                    <legend className="text-xs font-bold tracking-[0.1em] text-(--ink-deep) uppercase">
                        Kode sekolah
                    </legend>
                    <p className="sr-only">
                        Kode sekolah contoh: {SCHOOL_CODE}
                    </p>
                    <div aria-hidden className="mt-3 flex gap-1.5">
                        {SCHOOL_CODE.split('').map((digit, column) => (
                            <div
                                key={column}
                                className="flex flex-col items-center gap-1"
                            >
                                <span className="font-code mb-1 grid h-8 w-6 place-items-center border border-(--ink) text-base font-medium">
                                    {digit}
                                </span>
                                {Array.from({ length: 10 }, (_, value) => (
                                    <Bubble
                                        key={value}
                                        size="sm"
                                        filled={String(value) === digit}
                                        delay={codeDelay(column)}
                                    >
                                        {value}
                                    </Bubble>
                                ))}
                            </div>
                        ))}
                    </div>
                </fieldset>

                <div className="flex flex-col gap-7">
                    <fieldset>
                        <legend className="text-xs font-bold tracking-[0.1em] text-(--ink-deep) uppercase">
                            Jenjang
                        </legend>
                        <p className="sr-only">Jenjang contoh: SMA</p>
                        <div
                            aria-hidden
                            className="mt-3 grid grid-cols-4 gap-x-3"
                        >
                            {LEVELS.map((level) => (
                                <span
                                    key={level}
                                    className="inline-flex items-center gap-2 text-sm font-semibold"
                                >
                                    <Bubble
                                        filled={level === 'SMA'}
                                        delay={levelDelay}
                                    />
                                    {level}
                                </span>
                            ))}
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend className="text-xs font-bold tracking-[0.1em] text-(--ink-deep) uppercase">
                            Modul — coba isi
                        </legend>
                        <div className="mt-2 grid grid-cols-1 gap-x-6 sm:grid-cols-2">
                            {modules.map((module, index) => {
                                const on = enabled.has(module.key);

                                return (
                                    <button
                                        key={module.key}
                                        type="button"
                                        aria-pressed={on}
                                        onClick={() => toggle(module.key)}
                                        className="flex min-h-11 items-center gap-2.5 text-left text-sm font-medium transition-colors hover:text-(--ink-deep)"
                                    >
                                        <Bubble
                                            filled={on}
                                            delay={
                                                touched ? 0 : moduleDelay(index)
                                            }
                                        />
                                        {module.name}
                                    </button>
                                );
                            })}
                        </div>
                        <p className="mt-2 text-xs leading-relaxed text-(--pencil)">
                            {enabled.size} dari {modules.length} modul
                            dinyalakan. Modul dinyalakan per sekolah, bukan
                            serentak untuk semua sekolah.
                        </p>
                    </fieldset>
                </div>
            </div>

            <fieldset className="mt-8 border-t border-(--ink) pt-5">
                <legend className="sr-only">Status pengajuan</legend>
                <ol className="grid grid-cols-3 gap-3">
                    {STATUSES.map((label, index) => {
                        const done = index < STATUSES_DONE;

                        return (
                            <li
                                key={label}
                                className="relative flex flex-col items-start gap-2"
                            >
                                {index < STATUSES.length - 1 && (
                                    <span
                                        aria-hidden
                                        className={`absolute top-2.5 right-0 left-7 h-px ${
                                            index < STATUSES_DONE - 1
                                                ? 'bg-(--graphite)'
                                                : 'bg-(--ink)'
                                        }`}
                                    />
                                )}
                                <Bubble
                                    filled={done}
                                    delay={statusDelay(index)}
                                />
                                <span
                                    className={`text-xs leading-snug font-semibold sm:text-sm ${
                                        done ? '' : 'text-(--ink-deep)'
                                    }`}
                                >
                                    {label}
                                    <span className="sr-only">
                                        {done ? ' (sudah)' : ' (belum)'}
                                    </span>
                                </span>
                            </li>
                        );
                    })}
                </ol>
                <p className="mt-4 text-sm leading-relaxed text-(--pencil)">
                    Pengajuan sedang ditinjau. Setelah disetujui, sekolah dibuat
                    beserta peran bawaannya, dan admin sekolah menerima email
                    aktivasi.
                </p>
            </fieldset>
        </figure>
    );
}

function SheetBand({
    id,
    title,
    aside,
}: {
    id: string;
    title: string;
    aside?: string;
}) {
    return (
        <div className="border-y-2 border-(--ink) bg-(--ink-tint)">
            <div className="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-6 sm:flex-row sm:items-end sm:justify-between sm:gap-8 sm:px-8 sm:py-8">
                <h2
                    id={id}
                    className="text-[2rem] leading-none font-extrabold tracking-[-0.02em] text-balance [font-stretch:75%] sm:text-[2.75rem]"
                >
                    {title}
                </h2>
                {aside && (
                    <p className="max-w-sm text-sm leading-relaxed text-(--ink-deep) sm:text-right">
                        {aside}
                    </p>
                )}
            </div>
        </div>
    );
}

function ModulesSection() {
    return (
        <section aria-labelledby="modul">
            <SheetBand
                id="modul"
                title="Yang tercatat di SIMAS"
                aside="Enam modul dalam satu ruang kerja sekolah. Setiap sekolah menyalakan yang ia perlukan."
            />
            <ol className="mx-auto grid max-w-7xl px-5 sm:px-8 lg:grid-cols-2">
                {modules.map((module, index) => (
                    <li
                        key={module.key}
                        className={`grid grid-cols-[auto_1fr] gap-x-5 border-b border-(--ink-line) py-8 sm:py-10 ${
                            index % 2 === 0
                                ? 'lg:border-r lg:pr-10'
                                : 'lg:pl-10'
                        }`}
                    >
                        <span aria-hidden className="pt-1.5">
                            <Bubble filled={false} />
                        </span>
                        <div>
                            <h3 className="text-2xl font-bold tracking-[-0.01em] [font-stretch:85%]">
                                {module.name}
                            </h3>
                            <p className="mt-2 max-w-md text-base leading-relaxed">
                                {module.summary}
                            </p>
                            <ul className="mt-4 flex flex-col gap-2">
                                {module.detail.map((line) => (
                                    <li
                                        key={line}
                                        className="flex gap-3 text-sm leading-relaxed text-(--pencil)"
                                    >
                                        <span
                                            aria-hidden
                                            className="mt-[0.45rem] size-2 shrink-0 rounded-full border border-(--ink)"
                                        />
                                        {line}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </li>
                ))}
            </ol>
        </section>
    );
}

function FlowSection() {
    return (
        <section aria-labelledby="alur" className="mt-24 sm:mt-32">
            <SheetBand
                id="alur"
                title="Dari pengajuan sampai admin masuk"
                aside="Sekolah tidak aktif sebelum ada orang dari tim kami yang meninjau pengajuannya."
            />
            <ol className="mx-auto grid max-w-7xl px-5 sm:px-8 lg:grid-cols-5">
                {steps.map((step, index) => {
                    const review = index === 3;

                    return (
                        <li
                            key={step.title}
                            className={`grid grid-cols-[2rem_1fr] content-start items-baseline gap-x-2 border-b border-(--ink-line) py-8 lg:border-b-0 lg:px-5 lg:py-10 ${
                                index > 0 ? 'lg:border-l' : 'lg:pl-0'
                            } ${review ? 'bg-(--ink-tint)/60' : ''}`}
                        >
                            <span className="font-code text-xl font-medium text-(--ink-deep)">
                                <span className="sr-only">Langkah </span>
                                {index + 1}
                            </span>
                            <h3 className="text-xl leading-tight font-bold [font-stretch:85%]">
                                {step.title}
                            </h3>
                            <p className="col-start-2 mt-2 text-sm leading-relaxed text-(--pencil)">
                                {step.body}
                            </p>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}

function RolesSection() {
    return (
        <section aria-labelledby="peran" className="mt-24 sm:mt-32">
            <SheetBand
                id="peran"
                title="Siapa mengisi apa"
                aside="Ringkasan peran bawaan. Admin sekolah melihat rincian izinnya di halaman Peran."
            />
            <div className="mx-auto max-w-7xl px-5 py-10 sm:px-8 sm:py-14">
                <table className="w-full border-collapse text-left">
                    <thead>
                        <tr className="border-b-2 border-(--ink)">
                            <th
                                scope="col"
                                className="py-3 pr-4 text-xs font-bold tracking-[0.1em] text-(--ink-deep) uppercase"
                            >
                                Pekerjaan
                            </th>
                            {roles.map((role) => (
                                <th
                                    key={role.key}
                                    scope="col"
                                    className="w-16 px-1 py-3 text-center text-xs font-bold text-(--ink-deep) sm:w-32 sm:text-sm"
                                >
                                    {role.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {roleTasks.map((row) => (
                            <tr
                                key={row.task}
                                className="border-b border-(--ink-line)"
                            >
                                <th
                                    scope="row"
                                    className="py-4 pr-4 text-sm font-medium sm:text-base"
                                >
                                    {row.task}
                                </th>
                                {roles.map((role) => {
                                    const grants = row.roles.includes(role.key);

                                    return (
                                        <td
                                            key={role.key}
                                            className="py-4 text-center"
                                        >
                                            <span className="inline-flex">
                                                <Bubble filled={grants} />
                                            </span>
                                            <span className="sr-only">
                                                {grants ? 'Ya' : 'Tidak'}
                                            </span>
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function RulesSection() {
    return (
        <section
            aria-labelledby="ketentuan"
            className="mx-auto mt-16 grid max-w-7xl gap-10 px-5 sm:mt-24 sm:px-8 lg:grid-cols-12"
        >
            <h2
                id="ketentuan"
                className="text-[2rem] leading-none font-extrabold tracking-[-0.02em] text-balance [font-stretch:75%] sm:text-[2.75rem] lg:col-span-4"
            >
                Ketentuan yang selalu berlaku
            </h2>
            <ol className="relative border-2 border-(--ink) p-6 sm:p-10 lg:col-span-8">
                <CornerMarks />
                {principles.map((principle, index) => (
                    <li
                        key={principle.title}
                        className="grid grid-cols-[2.5rem_1fr] gap-x-3 border-b border-(--ink-line) py-6 first:pt-2 last:border-b-0 last:pb-2"
                    >
                        <span className="font-code pt-0.5 text-lg font-medium text-(--ink-deep)">
                            {index + 1}.
                        </span>
                        <div>
                            <h3 className="text-lg font-bold">
                                {principle.title}
                            </h3>
                            <p className="mt-1.5 max-w-xl text-[15px] leading-relaxed text-(--pencil)">
                                {principle.body}
                            </p>
                        </div>
                    </li>
                ))}
            </ol>
        </section>
    );
}

function CloseSection() {
    return (
        <section
            aria-labelledby="ajukan"
            className="relative mt-24 bg-(--ink) text-white sm:mt-32"
        >
            <CornerMarks tone="white" />
            <div className="mx-auto grid max-w-7xl gap-10 px-5 py-20 sm:px-8 sm:py-28 lg:grid-cols-12">
                <h2
                    id="ajukan"
                    className="text-[2.5rem] leading-[0.95] font-extrabold tracking-[-0.025em] text-balance [font-stretch:72%] sm:text-[4rem] lg:col-span-7"
                >
                    Ajukan sekolah Anda. Tim kami meninjau setiap pengajuan.
                </h2>
                <div className="flex flex-col items-start gap-6 lg:col-span-5 lg:pt-3">
                    <p className="text-lg leading-relaxed font-medium text-white">
                        Buat akun pemohon, isi data sekolah, lalu tunggu email
                        dari tim kami. Pengajuan yang ditolak dapat diperbaiki
                        dan dikirim lagi.
                    </p>
                    <PrimaryButton href={links.register} inverted>
                        Daftarkan sekolah
                    </PrimaryButton>
                    <div className="flex flex-col gap-1 text-[15px]">
                        <Link
                            href={links.applicantLogin}
                            className="inline-flex min-h-11 items-center underline decoration-white/50 underline-offset-4 hover:decoration-white"
                        >
                            Sudah mengajukan? Masuk sebagai pemohon
                        </Link>
                        <Link
                            href={links.ppdbRegister}
                            className="inline-flex min-h-11 items-center underline decoration-white/50 underline-offset-4 hover:decoration-white"
                        >
                            Calon siswa? Daftar PPDB di sini
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    );
}

function Footer() {
    return (
        <footer className="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-10 text-sm text-(--pencil) sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <span>
                <Wordmark className="text-sm text-(--graphite)" /> · Sistem
                Informasi Manajemen Sekolah
            </span>
            <nav aria-label="Kaki halaman" className="flex flex-wrap gap-x-5">
                <Link
                    href={links.schoolLogin}
                    className="inline-flex min-h-11 items-center hover:text-(--graphite)"
                >
                    Masuk sekolah
                </Link>
                <Link
                    href={links.applicantLogin}
                    className="inline-flex min-h-11 items-center hover:text-(--graphite)"
                >
                    Masuk pemohon
                </Link>
                <Link
                    href={links.ppdbRegister}
                    className="inline-flex min-h-11 items-center hover:text-(--graphite)"
                >
                    Calon siswa
                </Link>
            </nav>
        </footer>
    );
}
