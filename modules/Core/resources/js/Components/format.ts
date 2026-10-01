const dateFormat = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

/** "14 Jul 2025" from an ISO date (yyyy-mm-dd). */
export function formatDate(iso: string): string {
    return dateFormat.format(new Date(`${iso}T00:00:00`));
}

/** "14 Jul 2025 – 20 Des 2025", or a single date when no end. */
export function formatRange(start: string, end: string | null): string {
    return end === null || end === start
        ? formatDate(start)
        : `${formatDate(start)} – ${formatDate(end)}`;
}
