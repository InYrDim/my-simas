import type { ReactNode } from 'react';

/**
 * Console icon set: authored 24px SVGs, one 1.75 stroke, round caps and
 * joins, drawn on `currentColor`. Decorative only (aria-hidden); every
 * control that uses one carries its own text or aria-label.
 */
function Svg({
    children,
    className = 'size-5',
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            className={`shrink-0 ${className}`}
        >
            {children}
        </svg>
    );
}

type IconProps = { className?: string };

export function DashboardIcon(props: IconProps) {
    return (
        <Svg {...props}>
            <rect x="3" y="3" width="7" height="9" rx="1" />
            <rect x="14" y="3" width="7" height="5" rx="1" />
            <rect x="14" y="12" width="7" height="9" rx="1" />
            <rect x="3" y="16" width="7" height="5" rx="1" />
        </Svg>
    );
}

export function SchoolIcon(props: IconProps) {
    return (
        <Svg {...props}>
            <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z" />
            <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" />
            <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2" />
            <path d="M10 6h4M10 10h4M10 14h4M10 18h4" />
        </Svg>
    );
}

export function InboxIcon(props: IconProps) {
    return (
        <Svg {...props}>
            <path d="M22 12h-6l-2 3h-4l-2-3H2" />
            <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z" />
        </Svg>
    );
}

export function CardIcon(props: IconProps) {
    return (
        <Svg {...props}>
            <rect x="2" y="5" width="20" height="14" rx="2" />
            <path d="M2 10h20" />
        </Svg>
    );
}

export function UsersIcon(props: IconProps) {
    return (
        <Svg {...props}>
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </Svg>
    );
}

export function ChevronDownIcon(props: IconProps) {
    return (
        <Svg {...props}>
            <path d="m6 9 6 6 6-6" />
        </Svg>
    );
}

export function PanelLeftIcon(props: IconProps) {
    return (
        <Svg {...props}>
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <path d="M9 3v18" />
        </Svg>
    );
}

export function LogoutIcon(props: IconProps) {
    return (
        <Svg {...props}>
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <path d="m16 17 5-5-5-5" />
            <path d="M21 12H9" />
        </Svg>
    );
}

export function MenuIcon(props: IconProps) {
    return (
        <Svg {...props}>
            <path d="M4 6h16M4 12h16M4 18h16" />
        </Svg>
    );
}

export function CloseIcon(props: IconProps) {
    return (
        <Svg {...props}>
            <path d="M18 6 6 18M6 6l12 12" />
        </Svg>
    );
}
