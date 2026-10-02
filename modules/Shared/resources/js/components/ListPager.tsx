import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import { Button } from '@shared/components/ui/button';

/** What the server sends about the page of a list it returned. */
export interface Pagination {
    page: number;
    lastPage: number;
    total: number;
    from: number;
    to: number;
}

type Filters = Record<string, string>;

function visit(url: string, filters: Filters, page = 1) {
    const query: Record<string, string> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (value !== '') {
            query[key] = value;
        }
    }

    if (page > 1) {
        query.page = String(page);
    }

    router.get(url, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

/**
 * Filter state for a server-side list: every change reloads the page's
 * props from `url` (after a short pause, so typing does not fire a
 * request per key). Starts from the filters the server echoed back.
 */
export function useListFilters(url: string, initial: Filters) {
    const [filters, setFilters] = useState(initial);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = setTimeout(() => visit(url, filters), 250);

        return () => clearTimeout(timer);
    }, [filters, url]);

    return {
        filters,
        set: (key: string, value: string) =>
            setFilters((current) => ({ ...current, [key]: value })),
    };
}

/** "1–25 dari 80" with previous/next, keeping the current filters. */
export default function ListPager({
    url,
    filters,
    pagination,
}: {
    url: string;
    filters: Filters;
    pagination: Pagination;
}) {
    if (pagination.total === 0) {
        return null;
    }

    return (
        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
            <span>
                {pagination.from}–{pagination.to} dari {pagination.total}
            </span>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={pagination.page <= 1}
                    onClick={() => visit(url, filters, pagination.page - 1)}
                >
                    Sebelumnya
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={pagination.page >= pagination.lastPage}
                    onClick={() => visit(url, filters, pagination.page + 1)}
                >
                    Berikutnya
                </Button>
            </div>
        </div>
    );
}
