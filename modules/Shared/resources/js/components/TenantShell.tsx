import { Link, usePage } from '@inertiajs/react';
import { ChevronRightIcon, LogOutIcon } from 'lucide-react';
import { DynamicIcon } from 'lucide-react/dynamic';
import type { IconName } from 'lucide-react/dynamic';
import type { ReactNode } from 'react';

import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Avatar, AvatarFallback } from '@shared/components/ui/avatar';
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
import { useTenant } from '@shared/hooks/useTenant';

interface NavLinkItem {
    label: string;
    href: string;
    match: 'exact' | 'prefix';
}

/** A sidebar entry as shared by the server (`tenantNav`). */
interface NavItem extends NavLinkItem {
    /** Kebab-case lucide icon name, loaded on demand. */
    icon: string;
    children: NavLinkItem[];
}

type SharedProps = {
    auth: { user: { name: string; email: string } | null };
    flash: { status?: string | null };
    tenantNav: NavItem[];
};

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

/**
 * Shell for every school-side page: the shared shadcn Sidebar (icon rail
 * on desktop, a sheet on phones) fed by the server-side navigation
 * registry. Knows nothing about which module owns an entry.
 */
export default function TenantShell({
    children,
    width = 'max-w-5xl',
    logoutHref = '/logout',
}: {
    children: ReactNode;
    width?: string;
    logoutHref?: string;
}) {
    const { url, props } = usePage<SharedProps>();
    const tenant = useTenant();
    const path = url.split('?')[0];
    const user = props.auth?.user ?? null;
    const schoolName = tenant?.name ?? 'SIMAS';

    return (
        <SidebarProvider defaultOpen={readSidebarOpen()}>
            <Sidebar collapsible="icon">
                <SidebarHeader className="h-16 justify-center">
                    <div className="flex items-center gap-3 px-2 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0">
                        <span className="flex size-9 shrink-0 items-center justify-center bg-primary text-sm font-semibold text-primary-foreground">
                            {initials(schoolName) || 'S'}
                        </span>
                        <span className="grid leading-tight group-data-[collapsible=icon]:hidden">
                            <span className="truncate text-sm font-semibold">
                                {schoolName}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                SIMAS
                            </span>
                        </span>
                    </div>
                </SidebarHeader>

                <SidebarContent>
                    <SidebarGroup>
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {(props.tenantNav ?? []).map((item) => {
                                    const icon = (
                                        <DynamicIcon
                                            name={item.icon as IconName}
                                            fallback={() => <span className="size-4" />}
                                        />
                                    );

                                    if (item.children.length === 0) {
                                        return (
                                            <SidebarMenuItem key={item.href}>
                                                <SidebarMenuButton
                                                    asChild
                                                    tooltip={item.label}
                                                    isActive={isCurrent(path, item)}
                                                >
                                                    <Link href={item.href}>
                                                        {icon}
                                                        <span>{item.label}</span>
                                                    </Link>
                                                </SidebarMenuButton>
                                            </SidebarMenuItem>
                                        );
                                    }

                                    const active = item.children.some((child) =>
                                        isCurrent(path, child),
                                    );

                                    return (
                                        <Collapsible
                                            key={item.href}
                                            asChild
                                            defaultOpen={active}
                                            className="group/collapsible"
                                        >
                                            <SidebarMenuItem>
                                                <CollapsibleTrigger asChild>
                                                    <SidebarMenuButton
                                                        tooltip={item.label}
                                                        isActive={active}
                                                    >
                                                        {icon}
                                                        <span>{item.label}</span>
                                                        <ChevronRightIcon className="ml-auto transition-transform group-data-[collapsible=icon]:hidden group-data-[state=open]/collapsible:rotate-90" />
                                                    </SidebarMenuButton>
                                                </CollapsibleTrigger>
                                                <CollapsibleContent>
                                                    <SidebarMenuSub>
                                                        {item.children.map((child) => (
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
                                    );
                                })}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                </SidebarContent>

                <SidebarFooter>
                    <SidebarMenu>
                        {user !== null && (
                            <SidebarMenuItem>
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
                                            {user.email}
                                        </span>
                                    </span>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        )}
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Keluar">
                                <Link href={logoutHref} method="post" as="button">
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
                        {schoolName}
                    </span>
                </header>

                <main className={`mx-auto w-full px-6 py-10 ${width}`}>
                    {props.flash?.status && (
                        <Alert className="mb-6">
                            <AlertDescription>{props.flash.status}</AlertDescription>
                        </Alert>
                    )}
                    {children}
                </main>
            </SidebarInset>
        </SidebarProvider>
    );
}
