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

import { Avatar, AvatarFallback } from '@shared/components/ui/avatar';
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
import { useCan } from '@shared/hooks/useCan';

import InvoicesPanel from './account/InvoicesPanel';
import { Row } from './account/PanelParts';
import PlanPanel from './account/PlanPanel';

type PanelKey = 'plan' | 'invoices' | 'help' | 'about';

const helpTopics = [
    [
        'Mengundang guru dan staf',
        'Buka Pengguna, pilih Undang, lalu isi email dan peran.',
    ],
    [
        'Mengimpor data siswa',
        'Unduh templat CSV di Impor Data, isi, unggah, lalu konfirmasi.',
    ],
    [
        'Lupa kata sandi',
        'Gunakan Lupa kata sandi di halaman masuk dengan kode sekolah dan email.',
    ],
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

function AboutPanel({
    schoolName,
    schoolCode,
}: {
    schoolName: string;
    schoolCode: string;
}) {
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
    const can = useCan();
    const canSeeBilling = can('platform.billing.view');
    const panel = open === null ? null : panels[open];
    // An account without an email signs in by username (NIS, NIP).
    const identity = user.email ?? user.username ?? '';

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <SidebarMenuButton size="lg" tooltip={user.name}>
                        <Avatar size="sm">
                            <AvatarFallback>
                                {initials(user.name)}
                            </AvatarFallback>
                        </Avatar>
                        <span className="grid flex-1 text-left leading-tight">
                            <span className="truncate text-sm font-medium">
                                {user.name}
                            </span>
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
                    {canSeeBilling && (
                        <>
                            <DropdownMenuGroup>
                                <DropdownMenuItem
                                    onSelect={() => setOpen('plan')}
                                >
                                    <CreditCardIcon />
                                    Paket & Langganan
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => setOpen('invoices')}
                                >
                                    <ReceiptTextIcon />
                                    Tagihan & Invoice
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                            <DropdownMenuSeparator />
                        </>
                    )}
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
                        <Link
                            href={logoutHref}
                            method="post"
                            as="button"
                            className="w-full"
                        >
                            <LogOutIcon />
                            Keluar
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog
                open={open !== null}
                onOpenChange={(next) => !next && setOpen(null)}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{panel?.title}</DialogTitle>
                        <DialogDescription>
                            {panel?.description}
                        </DialogDescription>
                    </DialogHeader>
                    {open === 'plan' && <PlanPanel active />}
                    {open === 'invoices' && <InvoicesPanel active />}
                    {open === 'help' && <HelpPanel />}
                    {open === 'about' && (
                        <AboutPanel
                            schoolName={schoolName}
                            schoolCode={schoolCode}
                        />
                    )}
                    {(open === 'help' || open === 'about') && (
                        <p className="text-xs text-muted-foreground">
                            Tampilan contoh — isi bantuan dan versi belum final.
                        </p>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
