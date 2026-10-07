import { Link } from '@inertiajs/react';
import { ChevronRightIcon } from 'lucide-react';

export type QuickAction = { label: string; href: string };

/**
 * "Aksi cepat": the things a person with the keys of the school does most,
 * as ruled rows that are always in view. Every entry is a link to a page
 * with its own address. Rows are at least 44px tall.
 */
export default function QuickActions({ actions }: { actions: QuickAction[] }) {
    if (actions.length === 0) {
        return null;
    }

    return (
        <section className="mt-5">
            <h2 className="text-xs font-medium text-muted-foreground">
                Aksi cepat
            </h2>
            <ul className="mt-2 border-t border-border">
                {actions.map((action) => (
                    <li key={action.href} className="border-b border-border">
                        <Link
                            href={action.href}
                            className="flex min-h-11 items-center justify-between gap-4 rounded-md py-2 text-sm text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            {action.label}
                            <ChevronRightIcon className="size-4 text-muted-foreground" />
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}
