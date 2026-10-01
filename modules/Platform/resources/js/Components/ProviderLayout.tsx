import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';

import { index as applicationsIndex } from '@/actions/Modules/Platform/App/Http/Controllers/ApplicationReviewController';
import { destroy as providerLogout } from '@/actions/Modules/Platform/App/Http/Controllers/Auth/ProviderAuthenticatedSessionController';
import {
    index as billingIndex,
    invoices as billingInvoices,
    plans as billingPlans,
    subscriptions as billingSubscriptions,
} from '@/actions/Modules/Platform/App/Http/Controllers/BillingController';
import ProviderHomeController from '@/actions/Modules/Platform/App/Http/Controllers/ProviderHomeController';
import { index as usersIndex } from '@/actions/Modules/Platform/App/Http/Controllers/ProviderUserController';
import { index as tenantsIndex } from '@/actions/Modules/Platform/App/Http/Controllers/TenantConsoleController';

import { consolePath } from './consolePath';
import {
    CardIcon,
    ChevronDownIcon,
    CloseIcon,
    DashboardIcon,
    InboxIcon,
    LogoutIcon,
    MenuIcon,
    PanelLeftIcon,
    SchoolIcon,
    UsersIcon,
} from './icons';

type IconComponent = (props: { className?: string }) => ReactNode;

interface NavLinkItem {
    label: string;
    href: string;
    /** `exact` for an index page that shares a prefix with its siblings. */
    match?: 'exact';
}

interface NavEntry extends NavLinkItem {
    icon: IconComponent;
    /** Sub-pages; the entry becomes an expandable group. */
    children?: NavLinkItem[];
}

const nav: NavEntry[] = [
    { label: 'Dashboard', href: consolePath(ProviderHomeController.url()), icon: DashboardIcon },
    { label: 'Tenant', href: consolePath(tenantsIndex.url()), icon: SchoolIcon },
    { label: 'Pengajuan', href: consolePath(applicationsIndex.url()), icon: InboxIcon },
    {
        label: 'Langganan',
        href: consolePath(billingIndex.url()),
        icon: CardIcon,
        children: [
            { label: 'Ringkasan', href: consolePath(billingIndex.url()), match: 'exact' },
            { label: 'Daftar langganan', href: consolePath(billingSubscriptions.url()) },
            { label: 'Paket', href: consolePath(billingPlans.url()) },
            { label: 'Tagihan', href: consolePath(billingInvoices.url()) },
        ],
    },
    { label: 'Pengguna', href: consolePath(usersIndex.url()), icon: UsersIcon },
];

const storageKey = 'console.sidebar.collapsed';

function readCollapsed(): boolean {
    try {
        return window.localStorage.getItem(storageKey) === '1';
    } catch {
        return false;
    }
}

function writeCollapsed(collapsed: boolean): void {
    try {
        window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
    } catch {
        // Storage can be blocked; the sidebar then simply resets on reload.
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

const focusRing =
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';

const rowBase = `flex min-h-11 w-full items-center gap-3 rounded-lg px-3 text-sm transition-colors ${focusRing}`;
const rowIdle = 'text-muted-foreground hover:bg-sidebar-accent hover:text-foreground';
const rowActive = 'bg-sidebar-accent font-semibold text-sidebar-accent-foreground';

/** Label bubble shown beside an icon-only row on hover or keyboard focus. */
function Tooltip({ children }: { children: ReactNode }) {
    return (
        <span
            aria-hidden="true"
            className="pointer-events-none absolute top-1/2 left-full z-30 ml-3 hidden -translate-y-1/2 rounded-lg border console-pop border-border bg-popover px-2.5 py-1.5 text-xs whitespace-nowrap text-popover-foreground group-focus-within/item:block group-hover/item:block"
        >
            {children}
        </span>
    );
}

function NavRow({
    entry,
    href,
    current,
    collapsed,
    onNavigate,
}: {
    entry: NavEntry;
    href: string;
    current: boolean;
    collapsed: boolean;
    onNavigate?: () => void;
}) {
    const Icon = entry.icon;

    return (
        <li className="group/item relative">
            <Link
                href={href}
                onClick={onNavigate}
                aria-current={current ? 'page' : undefined}
                className={`${rowBase} ${collapsed ? 'justify-center px-0' : ''} ${current ? rowActive : rowIdle}`}
            >
                <Icon className={`size-5 ${current ? 'text-primary' : ''}`} />
                <span className={collapsed ? 'sr-only' : 'truncate'}>
                    {entry.label}
                </span>
            </Link>
            {collapsed && <Tooltip>{entry.label}</Tooltip>}
        </li>
    );
}

function NavGroup({
    entry,
    path,
    collapsed,
    open,
    onToggle,
    onNavigate,
}: {
    entry: NavEntry & { children: NavLinkItem[] };
    path: string;
    collapsed: boolean;
    open: boolean;
    onToggle: () => void;
    onNavigate?: () => void;
}) {
    const containsCurrent = entry.children.some((child) => isCurrent(path, child));

    if (collapsed) {
        return (
            <NavRow
                entry={entry}
                href={entry.href}
                current={containsCurrent}
                collapsed
                onNavigate={onNavigate}
            />
        );
    }

    const Icon = entry.icon;
    const listId = `nav-group-${entry.label.toLowerCase()}`;

    return (
        <li>
            <button
                type="button"
                onClick={onToggle}
                aria-expanded={open}
                aria-controls={listId}
                className={`${rowBase} ${containsCurrent && !open ? rowActive : rowIdle}`}
            >
                <Icon className={`size-5 ${containsCurrent ? 'text-primary' : ''}`} />
                <span className="flex-1 truncate text-left">{entry.label}</span>
                <ChevronDownIcon
                    className={`size-4 transition-transform motion-reduce:transition-none ${open ? 'rotate-180' : ''}`}
                />
            </button>

            {open && (
                <ul id={listId} className="mt-1 ml-5 border-l border-sidebar-border pl-3">
                    {entry.children.map((child) => {
                        const current = isCurrent(path, child);

                        return (
                            <li key={child.href}>
                                <Link
                                    href={child.href}
                                    onClick={onNavigate}
                                    aria-current={current ? 'page' : undefined}
                                    className={`${rowBase} ${current ? rowActive : rowIdle}`}
                                >
                                    <span className="truncate">{child.label}</span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </li>
    );
}

function Operator({
    operator,
    collapsed,
}: {
    operator: { name: string; email: string } | null;
    collapsed: boolean;
}) {
    if (operator === null) {
        return null;
    }

    return (
        <div className={`flex min-w-0 items-center gap-3 ${collapsed ? 'justify-center' : 'flex-1'}`}>
            <span
                aria-hidden="true"
                className="flex size-9 shrink-0 items-center justify-center rounded-md border border-input bg-muted text-xs font-semibold text-foreground"
            >
                {initials(operator.name)}
            </span>
            {!collapsed && (
                <span className="min-w-0">
                    <span className="block truncate text-sm font-medium text-foreground">
                        {operator.name}
                    </span>
                    <span className="block truncate text-xs text-muted-foreground">
                        {operator.email}
                    </span>
                </span>
            )}
        </div>
    );
}

function SidebarContent({
    path,
    operator,
    collapsed,
    onNavigate,
    onToggleCollapsed,
    onClose,
}: {
    path: string;
    operator: { name: string; email: string } | null;
    collapsed: boolean;
    onNavigate?: () => void;
    onToggleCollapsed?: () => void;
    onClose?: () => void;
}) {
    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({});

    return (
        <>
            <div
                className={`flex h-16 shrink-0 items-center ${collapsed ? 'justify-center' : 'justify-between pr-3 pl-6'}`}
            >
                {!collapsed && (
                    <span className="truncate text-sm font-semibold text-foreground">
                        Console Provider
                    </span>
                )}

                {onToggleCollapsed !== undefined && (
                    <button
                        type="button"
                        onClick={onToggleCollapsed}
                        aria-label={collapsed ? 'Lebarkan menu' : 'Ciutkan menu'}
                        aria-expanded={!collapsed}
                        title={collapsed ? 'Lebarkan menu' : 'Ciutkan menu'}
                        className={`flex size-11 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground ${focusRing}`}
                    >
                        <PanelLeftIcon />
                    </button>
                )}

                {onClose !== undefined && (
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Tutup menu"
                        className={`flex size-11 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground ${focusRing}`}
                    >
                        <CloseIcon />
                    </button>
                )}
            </div>

            <nav
                aria-label="Navigasi utama"
                className={`flex-1 px-3 pb-4 ${collapsed ? '' : 'overflow-y-auto'}`}
            >
                <ul className="flex flex-col gap-1">
                    {nav.map((entry) => {
                        if (entry.children !== undefined) {
                            const containsCurrent = entry.children.some((child) =>
                                isCurrent(path, child),
                            );

                            return (
                                <NavGroup
                                    key={entry.label}
                                    entry={entry as NavEntry & { children: NavLinkItem[] }}
                                    path={path}
                                    collapsed={collapsed}
                                    open={openGroups[entry.label] ?? containsCurrent}
                                    onToggle={() =>
                                        setOpenGroups((current) => ({
                                            ...current,
                                            [entry.label]: !(
                                                current[entry.label] ?? containsCurrent
                                            ),
                                        }))
                                    }
                                    onNavigate={onNavigate}
                                />
                            );
                        }

                        return (
                            <NavRow
                                key={entry.label}
                                entry={entry}
                                href={entry.href}
                                current={isCurrent(path, entry)}
                                collapsed={collapsed}
                                onNavigate={onNavigate}
                            />
                        );
                    })}
                </ul>
            </nav>

            <div
                className={`flex shrink-0 border-t border-sidebar-border p-3 ${collapsed ? 'flex-col items-center gap-2' : 'items-center gap-2'}`}
            >
                <Operator operator={operator} collapsed={collapsed} />

                <div className="group/item relative">
                    <Link
                        href={consolePath(providerLogout.url())}
                        method="post"
                        as="button"
                        aria-label="Keluar"
                        title={collapsed ? undefined : 'Keluar'}
                        className={`flex size-11 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground ${focusRing}`}
                    >
                        <LogoutIcon />
                    </Link>
                    {collapsed && <Tooltip>Keluar</Tooltip>}
                </div>
            </div>
        </>
    );
}

/**
 * Night-ground shell for every provider console page. Desktop (`sm`+):
 * a sticky left sidebar that expands (icon + label) or collapses to an
 * icon rail, remembered per browser. Below `sm`: a thin strip with a
 * menu button that opens the same navigation as an off-canvas drawer.
 */
export default function ProviderLayout({
    children,
    width = 'max-w-5xl',
}: {
    children: ReactNode;
    width?: string;
}) {
    const { url, props } = usePage<{
        auth: { provider: { name: string; email: string } | null };
    }>();
    const path = url.split('?')[0];
    const operator = props.auth.provider;

    const [collapsed, setCollapsed] = useState(readCollapsed);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const drawerRef = useRef<HTMLDivElement>(null);
    const menuButtonRef = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        setDrawerOpen(false);
    }, [path]);

    useEffect(() => {
        if (!drawerOpen) {
            return;
        }

        const opener = menuButtonRef.current;
        const overflow = document.body.style.overflow;

        document.body.style.overflow = 'hidden';
        drawerRef.current?.focus();

        return () => {
            document.body.style.overflow = overflow;
            opener?.focus();
        };
    }, [drawerOpen]);

    function toggleCollapsed() {
        const next = !collapsed;

        setCollapsed(next);
        writeCollapsed(next);
    }

    function onDrawerKeyDown(event: KeyboardEvent<HTMLDivElement>) {
        if (event.key === 'Escape') {
            setDrawerOpen(false);

            return;
        }

        if (event.key !== 'Tab' || drawerRef.current === null) {
            return;
        }

        const focusable = drawerRef.current.querySelectorAll<HTMLElement>(
            'a[href], button:not([disabled])',
        );
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    return (
        <div className="console-theme min-h-[100dvh] bg-background text-foreground sm:flex">
            <div className="sticky top-0 z-20 flex h-12 items-center gap-2 border-b border-sidebar-border bg-sidebar pr-6 pl-3 sm:hidden">
                <button
                    ref={menuButtonRef}
                    type="button"
                    onClick={() => setDrawerOpen(true)}
                    aria-label="Buka menu"
                    aria-expanded={drawerOpen}
                    className={`flex size-11 items-center justify-center rounded-lg text-foreground hover:bg-sidebar-accent ${focusRing}`}
                >
                    <MenuIcon />
                </button>
                <span className="text-sm font-semibold text-foreground">
                    Console Provider
                </span>
            </div>

            <aside
                className={`sticky top-0 hidden h-[100dvh] shrink-0 flex-col border-r border-sidebar-border bg-sidebar transition-[width] duration-200 ease-out motion-reduce:transition-none sm:flex ${collapsed ? 'w-[4.5rem]' : 'w-64'}`}
            >
                <SidebarContent
                    path={path}
                    operator={operator}
                    collapsed={collapsed}
                    onToggleCollapsed={toggleCollapsed}
                />
            </aside>

            {drawerOpen && (
                <div className="fixed inset-0 z-40 sm:hidden">
                    <div
                        className="absolute inset-0 bg-foreground/40"
                        onClick={() => setDrawerOpen(false)}
                        aria-hidden="true"
                    />
                    <div
                        ref={drawerRef}
                        role="dialog"
                        aria-modal="true"
                        aria-label="Menu"
                        tabIndex={-1}
                        onKeyDown={onDrawerKeyDown}
                        className="absolute inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col border-r border-sidebar-border bg-sidebar outline-none"
                    >
                        <SidebarContent
                            path={path}
                            operator={operator}
                            collapsed={false}
                            onNavigate={() => setDrawerOpen(false)}
                            onClose={() => setDrawerOpen(false)}
                        />
                    </div>
                </div>
            )}

            <main className="min-w-0 flex-1">
                <div className={`mx-auto px-6 py-10 ${width}`}>{children}</div>
            </main>
        </div>
    );
}
