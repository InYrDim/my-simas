import { Link, usePage } from "@inertiajs/react";
import { ChevronRightIcon } from "lucide-react";
import { DynamicIcon } from "lucide-react/dynamic";
import type { IconName } from "lucide-react/dynamic";
import type { ReactNode } from "react";

import AccountMenu from "@shared/components/AccountMenu";
import { Alert, AlertDescription } from "@shared/components/ui/alert";
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from "@shared/components/ui/collapsible";
import { Separator } from "@shared/components/ui/separator";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
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
} from "@shared/components/ui/sidebar";
import { useTenant } from "@shared/hooks/useTenant";

interface NavLinkItem {
    label: string;
    href: string;
    match: "exact" | "prefix";
}

/** A sidebar entry as shared by the server (`tenantNav`). */
interface NavItem extends NavLinkItem {
    /** Kebab-case lucide icon name, loaded on demand. */
    icon: string;
    /** Section heading; entries sharing one render together. */
    group: string | null;
    children: NavLinkItem[];
}

type SharedProps = {
    auth: { user: { name: string; email: string } | null };
    flash: { status?: string | null };
    tenantNav: NavItem[];
};

const sidebarCookie = "sidebar_state";

function readSidebarOpen(): boolean {
    try {
        return !document.cookie.includes(`${sidebarCookie}=false`);
    } catch {
        return true;
    }
}

function isCurrent(path: string, item: NavLinkItem): boolean {
    return item.match === "exact"
        ? path === item.href
        : path === item.href || path.startsWith(`${item.href}/`);
}

/** Buckets entries by section, in order of each section's first entry. */
function groupNav(
    items: NavItem[],
): { label: string | null; items: NavItem[] }[] {
    const sections: { label: string | null; items: NavItem[] }[] = [];

    for (const item of items) {
        const section = sections.find((s) => s.label === item.group);

        if (section) {
            section.items.push(item);
        } else {
            sections.push({ label: item.group, items: [item] });
        }
    }

    return sections;
}

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join("");
}

/**
 * Shell for every school-side page: the shared shadcn Sidebar (icon rail
 * on desktop, a sheet on phones) fed by the server-side navigation
 * registry. Knows nothing about which module owns an entry.
 */
export default function TenantShell({
    children,
    width = "max-w-5xl",
    logoutHref = "/logout",
}: {
    children: ReactNode;
    width?: string;
    logoutHref?: string;
}) {
    const { url, props } = usePage<SharedProps>();
    const tenant = useTenant();
    const path = url.split("?")[0];
    const user = props.auth?.user ?? null;
    const schoolName = tenant?.name ?? "SIMAS";

    return (
        <SidebarProvider defaultOpen={readSidebarOpen()}>
            <Sidebar collapsible="icon">
                <SidebarHeader className="h-16 justify-center">
                    <div className="flex items-center gap-3 px-2 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0">
                        <span className="flex size-9 shrink-0 items-center justify-center bg-primary text-sm font-semibold text-primary-foreground">
                            {initials(schoolName) || "S"}
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
                    {groupNav(props.tenantNav ?? []).map((section) => (
                        <SidebarGroup key={section.label ?? "ungrouped"}>
                            {section.label && (
                                <SidebarGroupLabel>
                                    {section.label}
                                </SidebarGroupLabel>
                            )}
                            <SidebarGroupContent>
                                <SidebarMenu>
                                    {section.items.map((item) => {
                                        const icon = (
                                            <DynamicIcon
                                                name={item.icon as IconName}
                                                fallback={() => (
                                                    <span className="size-4" />
                                                )}
                                            />
                                        );

                                        if (item.children.length === 0) {
                                            return (
                                                <SidebarMenuItem
                                                    key={item.href}
                                                >
                                                    <SidebarMenuButton
                                                        asChild
                                                        tooltip={item.label}
                                                        isActive={isCurrent(
                                                            path,
                                                            item,
                                                        )}
                                                    >
                                                        <Link href={item.href}>
                                                            {icon}
                                                            <span>
                                                                {item.label}
                                                            </span>
                                                        </Link>
                                                    </SidebarMenuButton>
                                                </SidebarMenuItem>
                                            );
                                        }

                                        // The most specific child wins, so /users/roles
                                        // does not also light up its /users sibling.
                                        const current = item.children
                                            .filter((child) =>
                                                isCurrent(path, child),
                                            )
                                            .sort(
                                                (a, b) =>
                                                    b.href.length -
                                                    a.href.length,
                                            )[0];
                                        const active = current !== undefined;

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
                                                            <span>
                                                                {item.label}
                                                            </span>
                                                            <ChevronRightIcon className="ml-auto transition-transform group-data-[collapsible=icon]:hidden group-data-[state=open]/collapsible:rotate-90" />
                                                        </SidebarMenuButton>
                                                    </CollapsibleTrigger>
                                                    <CollapsibleContent>
                                                        <SidebarMenuSub>
                                                            {item.children.map(
                                                                (child) => (
                                                                    <SidebarMenuSubItem
                                                                        key={
                                                                            child.href
                                                                        }
                                                                    >
                                                                        <SidebarMenuSubButton
                                                                            asChild
                                                                            isActive={
                                                                                child ===
                                                                                current
                                                                            }
                                                                        >
                                                                            <Link
                                                                                href={
                                                                                    child.href
                                                                                }
                                                                            >
                                                                                <span>
                                                                                    {
                                                                                        child.label
                                                                                    }
                                                                                </span>
                                                                            </Link>
                                                                        </SidebarMenuSubButton>
                                                                    </SidebarMenuSubItem>
                                                                ),
                                                            )}
                                                        </SidebarMenuSub>
                                                    </CollapsibleContent>
                                                </SidebarMenuItem>
                                            </Collapsible>
                                        );
                                    })}
                                </SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    ))}
                </SidebarContent>

                <SidebarFooter>
                    <SidebarMenu>
                        {user !== null && (
                            <SidebarMenuItem>
                                <AccountMenu
                                    user={user}
                                    schoolName={schoolName}
                                    schoolCode={tenant?.slug ?? ""}
                                    logoutHref={logoutHref}
                                />
                            </SidebarMenuItem>
                        )}
                    </SidebarMenu>
                </SidebarFooter>
                <SidebarRail />
            </Sidebar>

            <SidebarInset>
                <header className="sticky top-0 z-10 flex h-14 shrink-0 items-center gap-3 border-b border-border bg-card/90 px-4 sm:px-6">
                    <SidebarTrigger aria-label="Buka atau tutup menu" />
                    <Separator orientation="vertical" className="h-4" />
                    <span className="truncate text-sm font-medium text-foreground">
                        {schoolName}
                    </span>
                </header>

                <main
                    className={`mx-auto w-full px-4 py-8 sm:px-6 sm:py-10 ${width}`}
                >
                    {props.flash?.status && (
                        <Alert className="mb-6">
                            <AlertDescription>
                                {props.flash.status}
                            </AlertDescription>
                        </Alert>
                    )}
                    {children}
                </main>
            </SidebarInset>
        </SidebarProvider>
    );
}
