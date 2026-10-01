import { Link, usePage } from '@inertiajs/react';
import {
    ChevronRightIcon,
    CreditCardIcon,
    InboxIcon,
    LayoutDashboardIcon,
    LogOutIcon,
    SchoolIcon,
    UsersIcon,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';

import { index as applicationsIndex } from '@/actions/Modules/Platform/App/Http/Controllers/ApplicationReviewController';
import { destroy as providerLogout } from '@/actions/Modules/Platform/App/Http/Controllers/Auth/ProviderAuthenticatedSessionController';
import {
    index as billingIndex,
    subscriptions as billingSubscriptions,
} from '@/actions/Modules/Platform/App/Http/Controllers/BillingController';
import { index as billingInvoices } from '@/actions/Modules/Platform/App/Http/Controllers/InvoiceController';
import { index as billingPlans } from '@/actions/Modules/Platform/App/Http/Controllers/PlanController';
import ProviderHomeController from '@/actions/Modules/Platform/App/Http/Controllers/ProviderHomeController';
import { index as tenantsIndex } from '@/actions/Modules/Platform/App/Http/Controllers/TenantConsoleController';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import {
    Avatar,
    AvatarFallback,
} from '@shared/components/ui/avatar';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@shared/components/ui/collapsible';
import { Separator } from '@shared/components/ui/separator';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarProvider,
    SidebarRail,
    SidebarTrigger,
} from '@shared/components/ui/sidebar';

import { consolePath } from './consolePath';

interface NavLinkItem {
    label: string;
    href: string;
    /** `exact` for an index page that shares a prefix with its siblings. */
    match?: 'exact';
}

interface NavEntry extends NavLinkItem {
    icon: ComponentType;
    /** Sub-pages; the entry becomes an expandable group. */
    children?: NavLinkItem[];
}

const nav: NavEntry[] = [
    {
        label: 'Dashboard',
        href: consolePath(ProviderHomeController.url()),
        icon: LayoutDashboardIcon,
    },
    { label: 'Tenant', href: consolePath(tenantsIndex.url()), icon: SchoolIcon },
    {
        label: 'Pengajuan',
        href: consolePath(applicationsIndex.url()),
        icon: InboxIcon,
    },
    {
        label: 'Langganan',
        href: consolePath(billingIndex.url()),
        icon: CreditCardIcon,
        children: [
            {
                label: 'Ringkasan',
                href: consolePath(billingIndex.url()),
                match: 'exact',
            },
            {
                label: 'Daftar langganan',
                href: consolePath(billingSubscriptions.url()),
            },
            { label: 'Paket', href: consolePath(billingPlans.url()) },
            { label: 'Tagihan', href: consolePath(billingInvoices.url()) },
        ],
    },
    // Served by the Identity module on the console host.
    { label: 'Pengguna', href: '/users', icon: UsersIcon },
];

const sidebarCookie = 'sidebar_state';

function readSidebarOpen(): boolean {
    try {
        return !document.cookie.includes(`${sidebarCookie}=false`);
    } catch {
        return true;
    }
}

function isCurrent(path: string, item: NavLinkItem): boolean {
    return item.match === 'exact'
        ? path === item.href
        : path === item.href || path.startsWith(`${item.href}/`);
}

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');
}

type SharedProps = {
    auth: { provider: { name: string; email: string } | null };
    flash: { status?: string | null };
    errors: Record<string, string>;
};

/**
 * Shell for every provider console page, built from the shared shadcn
 * Sidebar: icon + label, collapsible to an icon rail on desktop, a sheet
 * on phones. Flash and billing errors render above the page content.
 */
export default function ProviderLayout({
    children,
    width = 'max-w-5xl',
}: {
    children: ReactNode;
    width?: string;
}) {
    const { url, props } = usePage<SharedProps>();
    const path = url.split('?')[0];
    const operator = props.auth.provider;

    return (
        <SidebarProvider defaultOpen={readSidebarOpen()}>
            <Sidebar collapsible="icon">
                <SidebarHeader className="h-16 justify-center">
                    <div className="flex items-center gap-3 px-2 group-data-[collapsible=icon]:px-0">
                        <span className="flex size-9 shrink-0 items-center justify-center bg-primary text-sm font-semibold text-primary-foreground group-data-[collapsible=icon]:mx-auto">
                            S
                        </span>
                        <span className="grid leading-tight group-data-[collapsible=icon]:hidden">
                            <span className="text-sm font-semibold">SIMAS</span>
                            <span className="text-xs text-muted-foreground">
                                Console Provider
                            </span>
                        </span>
                    </div>
                </SidebarHeader>

                <SidebarContent>
                    <SidebarGroup>
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {nav.map((entry) =>
                                    entry.children === undefined ? (
                                        <SidebarMenuItem key={entry.label}>
                                            <SidebarMenuButton
                                                asChild
                                                tooltip={entry.label}
                                                isActive={isCurrent(path, entry)}
                                            >
                                                <Link href={entry.href}>
                                                    <entry.icon />
                                                    <span>{entry.label}</span>
                                                </Link>
                                            </SidebarMenuButton>
                                        </SidebarMenuItem>
                                    ) : (
                                        <Collapsible
                                            key={entry.label}
                                            asChild
                                            defaultOpen={entry.children.some((child) =>
                                                isCurrent(path, child),
                                            )}
                                            className="group/collapsible"
                                        >
                                            <SidebarMenuItem>
                                                <CollapsibleTrigger asChild>
                                                    <SidebarMenuButton
                                                        tooltip={entry.label}
                                                        isActive={entry.children.some(
                                                            (child) => isCurrent(path, child),
                                                        )}
                                                    >
                                                        <entry.icon />
                                                        <span>{entry.label}</span>
                                                        <ChevronRightIcon className="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
                                                    </SidebarMenuButton>
                                                </CollapsibleTrigger>
                                                <CollapsibleContent>
                                                    <SidebarMenuSub>
                                                        {entry.children.map((child) => (
                                                            <SidebarMenuSubItem key={child.href}>
                                                                <SidebarMenuSubButton
                                                                    asChild
                                                                    isActive={isCurrent(path, child)}
                                                                >
                                                                    <Link href={child.href}>
                                                                        <span>{child.label}</span>
                                                                    </Link>
                                                                </SidebarMenuSubButton>
                                                            </SidebarMenuSubItem>
                                                        ))}
                                                    </SidebarMenuSub>
                                                </CollapsibleContent>
                                            </SidebarMenuItem>
                                        </Collapsible>
                                    ),
                                )}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                </SidebarContent>

                <SidebarFooter>
                    <SidebarMenu>
                        {operator !== null && (
                            <SidebarMenuItem>
                                <SidebarMenuButton size="lg" tooltip={operator.name}>
                                    <Avatar size="sm">
                                        <AvatarFallback>
                                            {initials(operator.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="grid flex-1 text-left leading-tight">
                                        <span className="truncate text-sm font-medium">
                                            {operator.name}
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            {operator.email}
                                        </span>
                                    </span>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        )}
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Keluar">
                                <Link
                                    href={consolePath(providerLogout.url())}
                                    method="post"
                                    as="button"
                                >
                                    <LogOutIcon />
                                    <span>Keluar</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarFooter>
                <SidebarRail />
            </Sidebar>

            <SidebarInset>
                <header className="flex h-16 shrink-0 items-center gap-3 px-6">
                    <SidebarTrigger aria-label="Buka atau tutup menu" />
                    <Separator orientation="vertical" className="h-4" />
                    <span className="text-sm text-muted-foreground">
                        Console Provider
                    </span>
                </header>

                <main className={`mx-auto w-full px-6 py-10 ${width}`}>
                    {props.flash?.status && (
                        <Alert className="mb-6">
                            <AlertDescription>{props.flash.status}</AlertDescription>
                        </Alert>
                    )}
                    {props.errors?.billing && (
                        <Alert variant="destructive" className="mb-6">
                            <AlertDescription>{props.errors.billing}</AlertDescription>
                        </Alert>
                    )}
                    {children}
                </main>
            </SidebarInset>
        </SidebarProvider>
    );
}
