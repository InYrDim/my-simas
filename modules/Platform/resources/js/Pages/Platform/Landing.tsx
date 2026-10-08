import { Head, Link } from '@inertiajs/react';
import { ArrowRightIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import {
    links,
    modules,
    principles,
    roles,
    roleTasks,
    steps,
} from '../../Components/Landing/content';
import { SchoolBuilding } from '../../Components/Landing/SchoolBuilding';
import {
    Bubble,
    CornerMarks,
    TimingRail,
    Wordmark,
} from '@shared/components/lembar/parts';

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
}: {
    href: string;
    children: ReactNode;
}) {
    return (
        <Link
            href={href}
            className="inline-flex h-12 items-center justify-center gap-2 rounded-[2px] bg-(--graphite) px-6 text-[15px] font-semibold text-white transition-colors hover:bg-(--ink-deep) active:translate-y-px"
        >
            {children}
        </Link>
    );
}

function Masthead() {
    return (
        <header className="bg-white">
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
            <div className="lg:col-span-5 lg:pt-6">
                <h1 className="text-[2.75rem] leading-[0.95] font-bold tracking-[-0.025em] text-balance [font-stretch:72%] sm:text-[4.25rem] xl:text-[5.25rem]">
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
                <p className="mt-8 max-w-[34rem] text-sm text-(--pencil)">
                    Trial {trialDays} hari, dimulai saat pengajuan disetujui.
                    Tanpa pembayaran di awal.
                </p>
            </div>

            <div className="lg:col-span-7">
                <SchoolBuilding />
            </div>
        </section>
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
        <div className="bg-(--ink-tint)">
            <div className="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-6 sm:flex-row sm:items-end sm:justify-between sm:gap-8 sm:px-8 sm:py-8">
                <h2
                    id={id}
                    className="text-[2rem] leading-none font-bold tracking-[-0.02em] text-balance [font-stretch:75%] sm:text-[2.75rem]"
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
                        className={`grid grid-cols-[auto_1fr] gap-x-5 py-8 sm:py-10 ${
                            index % 2 === 0 ? 'lg:pr-10' : 'lg:pl-10'
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
                            className={`grid grid-cols-[2rem_1fr] content-start items-baseline gap-x-2 py-6 lg:px-5 lg:py-10 ${
                                index > 0 ? '' : 'lg:pl-0'
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
                        <tr>
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
                            <tr key={row.task} className="even:bg-white">
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
                className="text-[2rem] leading-none font-bold tracking-[-0.02em] text-balance [font-stretch:75%] sm:text-[2.75rem] lg:col-span-4"
            >
                Ketentuan yang selalu berlaku
            </h2>
            <ol className="relative bg-white p-6 sm:p-10 lg:col-span-8">
                <CornerMarks />
                {principles.map((principle, index) => (
                    <li
                        key={principle.title}
                        className="grid grid-cols-[2.5rem_1fr] gap-x-3 py-5 first:pt-2 last:pb-2"
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
            className="relative mt-24 bg-(--ink-tint) sm:mt-32"
        >
            <CornerMarks />
            <div className="mx-auto grid max-w-7xl gap-10 px-5 py-20 sm:px-8 sm:py-28 lg:grid-cols-12">
                <h2
                    id="ajukan"
                    className="text-[2.5rem] leading-[0.95] font-bold tracking-[-0.02em] text-balance text-(--ink-deep) [font-stretch:72%] sm:text-[4rem] lg:col-span-7"
                >
                    Ajukan sekolah Anda. Tim kami meninjau setiap pengajuan.
                </h2>
                <div className="flex flex-col items-start gap-6 lg:col-span-5 lg:pt-3">
                    <p className="text-lg leading-relaxed text-(--pencil)">
                        Buat akun pemohon, isi data sekolah, lalu tunggu email
                        dari tim kami. Pengajuan yang ditolak dapat diperbaiki
                        dan dikirim lagi.
                    </p>
                    <PrimaryButton href={links.register}>
                        Daftarkan sekolah
                    </PrimaryButton>
                    <div className="flex flex-col gap-1 text-[15px]">
                        <Link
                            href={links.applicantLogin}
                            className="inline-flex min-h-11 items-center underline decoration-(--ink-line) underline-offset-4 transition-colors hover:text-(--ink-deep) hover:decoration-(--ink)"
                        >
                            Sudah mengajukan? Masuk sebagai pemohon
                        </Link>
                        <Link
                            href={links.ppdbRegister}
                            className="inline-flex min-h-11 items-center underline decoration-(--ink-line) underline-offset-4 transition-colors hover:text-(--ink-deep) hover:decoration-(--ink)"
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
