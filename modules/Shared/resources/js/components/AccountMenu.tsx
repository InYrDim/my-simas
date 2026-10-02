import { Link } from '@inertiajs/react';
import {
    ChevronsUpDownIcon,
    CircleHelpIcon,
    CreditCardIcon,
    InfoIcon,
    KeyRoundIcon,
    LogOutIcon,
    ReceiptTextIcon,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import { Avatar, AvatarFallback } from '@shared/components/ui/avatar';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@shared/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@shared/components/ui/dropdown-menu';
import { SidebarMenuButton } from '@shared/components/ui/sidebar';

type PanelKey = 'plan' | 'invoices' | 'help' | 'about';

/*
 * Sample content: the account panels are a mockup, nothing is persisted
 * and the figures are placeholders until Platform exposes real billing.
 */
const usage = [
    { label: 'Siswa', used: 642, limit: 1000 },
    { label: 'Akun staf', used: 38, limit: 60 },
    { label: 'Penyimpanan (GB)', used: 3, limit: 10 },
];

const invoices = [
    { number: 'INV-2026-0010', date: '1 Oktober 2026', status: 'unpaid' },
    { number: 'INV-2026-0009', date: '1 September 2026', status: 'paid' },
    { number: 'INV-2026-0008', date: '1 Agustus 2026', status: 'paid' },
    { number: 'INV-2026-0007', date: '1 Juli 2026', status: 'paid' },
] as const;

const helpTopics = [
    ['Mengundang guru dan staf', 'Buka Pengguna, pilih Undang, lalu isi email dan peran.'],
    ['Mengimpor data siswa', 'Unduh templat CSV di Impor Data, isi, unggah, lalu konfirmasi.'],
    ['Lupa kata sandi', 'Gunakan Lupa kata sandi di halaman masuk dengan kode sekolah dan email.'],
];

const panels: Record<PanelKey, { title: string; description: string }> = {
    plan: {
        title: 'Paket & Langganan',
        description: 'Paket, pemakaian, dan modul sekolah Anda.',
    },
    invoices: {
        title: 'Tagihan & Invoice',
        description: 'Riwayat tagihan langganan.',
    },
    help: {
        title: 'Pusat Bantuan',
        description: 'Jawaban singkat untuk pertanyaan yang sering muncul.',
    },
    about: {
        title: 'Tentang SIMAS',
        description: 'Versi aplikasi dan data untuk keperluan dukungan.',
    },
};

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');
}

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex items-baseline justify-between gap-6 border-b border-border py-2.5 text-sm last:border-b-0">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right text-foreground">{children}</dd>
        </div>
    );
}

function PlanPanel() {
    return (
        <div className="flex flex-col gap-5">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-base font-semibold">Sekolah Standar</p>
                    <p className="text-muted-foreground">Rp 750.000 per bulan</p>
                </div>
                <Badge>Berlangganan</Badge>
            </div>

            <div className="flex flex-col gap-4">
                {usage.map((row) => {
                    const percent = Math.round((row.used / row.limit) * 100);

                    return (
                        <div key={row.label}>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">{row.label}</span>
                                <span className="font-medium">
                                    {row.used} / {row.limit}
                                </span>
                            </div>
                            <div
                                role="progressbar"
                                aria-label={row.label}
                                aria-valuenow={row.used}
                                aria-valuemin={0}
                                aria-valuemax={row.limit}
                                className="mt-1.5 h-1.5 bg-muted"
                            >
                                <div
                                    className={percent >= 90 ? 'h-full bg-accent' : 'h-full bg-primary'}
                                    style={{ width: `${percent}%` }}
                                />
                            </div>
                        </div>
                    );
                })}
            </div>

            <dl className="border-t border-border">
                <Row label="Modul aktif">Core · Absensi</Row>
                <Row label="Perpanjangan berikutnya">1 November 2026</Row>
            </dl>
        </div>
    );
}

function InvoicesPanel() {
    return (
        <ul className="flex flex-col divide-y divide-border">
            {invoices.map((invoice) => (
                <li key={invoice.number} className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                    <div>
                        <p className="font-mono text-xs">{invoice.number}</p>
                        <p className="text-xs text-muted-foreground">{invoice.date} · Rp 750.000</p>
                    </div>
                    {invoice.status === 'unpaid' ? (
                        <div className="flex items-center gap-3">
                            <Badge variant="outline">Belum dibayar</Badge>
                            <Button size="sm">Bayar</Button>
                        </div>
                    ) : (
                        <Badge>Lunas</Badge>
                    )}
                </li>
            ))}
        </ul>
    );
}

function HelpPanel() {
    return (
        <div className="flex flex-col gap-4">
            <ul className="flex flex-col divide-y divide-border">
                {helpTopics.map(([title, body]) => (
                    <li key={title} className="py-3 first:pt-0 last:pb-0">
                        <p className="font-medium">{title}</p>
                        <p className="mt-0.5 text-muted-foreground">{body}</p>
                    </li>
                ))}
            </ul>
            <Button asChild>
                <a href="mailto:bantuan@simas.id">Hubungi bantuan</a>
            </Button>
        </div>
    );
}

function AboutPanel({ schoolName, schoolCode }: { schoolName: string; schoolCode: string }) {
    return (
        <dl>
            <Row label="Aplikasi">SIMAS</Row>
            <Row label="Versi">0.9.0 (pratinjau)</Row>
            <Row label="Sekolah">{schoolName}</Row>
            <Row label="Kode sekolah">
                <span className="font-mono">{schoolCode}</span>
            </Row>
        </dl>
    );
}

/**
 * The profile button at the foot of the sidebar. It opens a menu of
 * account choices (plan, invoices, help, about, sign out); each choice
 * opens as a dialog over the current page instead of navigating away.
 */
export default function AccountMenu({
    user,
    schoolName,
    schoolCode,
    logoutHref,
    changePasswordHref,
}: {
    user: { name: string; email: string | null; username?: string | null };
    schoolName: string;
    schoolCode: string;
    logoutHref: string;
    changePasswordHref: string;
}) {
    const [open, setOpen] = useState<PanelKey | null>(null);
    const panel = open === null ? null : panels[open];
    // An account without an email signs in by username (NIS, NIP).
    const identity = user.email ?? user.username ?? '';

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <SidebarMenuButton size="lg" tooltip={user.name}>
                        <Avatar size="sm">
                            <AvatarFallback>{initials(user.name)}</AvatarFallback>
                        </Avatar>
                        <span className="grid flex-1 text-left leading-tight">
                            <span className="truncate text-sm font-medium">{user.name}</span>
                            <span className="truncate text-xs text-muted-foreground">
                                {identity}
                            </span>
                        </span>
                        <ChevronsUpDownIcon className="ml-auto group-data-[collapsible=icon]:hidden" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    side="top"
                    align="start"
                    className="min-w-60"
                >
                    <DropdownMenuLabel className="font-normal">
                        <span className="block truncate text-sm font-medium text-foreground">
                            {user.name}
                        </span>
                        <span className="block truncate text-xs text-muted-foreground">
                            {identity}
                        </span>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => setOpen('plan')}>
                            <CreditCardIcon />
                            Paket & Langganan
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={() => setOpen('invoices')}>
                            <ReceiptTextIcon />
                            Tagihan & Invoice
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => setOpen('help')}>
                            <CircleHelpIcon />
                            Pusat Bantuan
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={() => setOpen('about')}>
                            <InfoIcon />
                            Tentang SIMAS
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem asChild>
                        <Link href={changePasswordHref}>
                            <KeyRoundIcon />
                            Ganti kata sandi
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href={logoutHref} method="post" as="button" className="w-full">
                            <LogOutIcon />
                            Keluar
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={open !== null} onOpenChange={(next) => !next && setOpen(null)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{panel?.title}</DialogTitle>
                        <DialogDescription>{panel?.description}</DialogDescription>
                    </DialogHeader>
                    {open === 'plan' && <PlanPanel />}
                    {open === 'invoices' && <InvoicesPanel />}
                    {open === 'help' && <HelpPanel />}
                    {open === 'about' && (
                        <AboutPanel schoolName={schoolName} schoolCode={schoolCode} />
                    )}
                    <p className="text-xs text-muted-foreground">
                        Tampilan contoh — data belum tersimpan.
                    </p>
                </DialogContent>
            </Dialog>
        </>
    );
}
