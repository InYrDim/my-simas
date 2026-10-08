import type { CSSProperties, ReactNode } from 'react';
import { useEffect, useState } from 'react';

import './lembar.css';

const TIMING_MARKS = 28;

/**
 * The sheet's one mark: a cobalt ring is an answer that exists, a graphite
 * disc is the answer chosen. Never decorative.
 */
export function Bubble({
    filled,
    delay,
    children,
    size = 'md',
}: {
    filled: boolean;
    delay?: number;
    children?: ReactNode;
    size?: 'sm' | 'md';
}) {
    const box = size === 'sm' ? 'size-4 text-[8px]' : 'size-5 text-[9px]';

    return (
        <span
            className={`relative inline-grid shrink-0 place-items-center rounded-full border border-(--ink) font-semibold text-(--ink-deep) ${box}`}
        >
            {children}
            {filled && (
                <span
                    className="pencil-fill absolute inset-[-1px] rounded-full bg-(--graphite)"
                    style={{ '--delay': `${delay ?? 0}ms` } as CSSProperties}
                />
            )}
        </span>
    );
}

/** The scanner's registration marks, only on a framed sheet or band. */
export function CornerMarks({
    tone = 'graphite',
}: {
    tone?: 'graphite' | 'white';
}) {
    const color = tone === 'white' ? 'bg-white' : 'bg-(--graphite)';

    return (
        <>
            {[
                'top-2 left-2',
                'top-2 right-2',
                'bottom-2 left-2',
                'bottom-2 right-2',
            ].map((position) => (
                <span
                    key={position}
                    aria-hidden
                    className={`absolute size-2.5 ${color} ${position}`}
                />
            ))}
        </>
    );
}

/**
 * The timing marks down the sheet's left edge. Given a `progress`
 * (0..1) they show how much of the form is filled in; without one they
 * follow the reading position on a long page.
 */
export function TimingRail({ progress }: { progress?: number }) {
    const [scrolled, setScrolled] = useState(1);
    const followsScroll = progress === undefined;

    useEffect(() => {
        if (!followsScroll) {
            return;
        }

        let frame = 0;

        const measure = () => {
            frame = 0;
            const max =
                document.documentElement.scrollHeight - window.innerHeight;
            const ratio = max > 0 ? window.scrollY / max : 1;
            setScrolled(Math.max(1, Math.round(ratio * TIMING_MARKS)));
        };

        const onScroll = () => {
            if (frame === 0) {
                frame = window.requestAnimationFrame(measure);
            }
        };

        measure();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);

        return () => {
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
            window.cancelAnimationFrame(frame);
        };
    }, [followsScroll]);

    const filled = followsScroll
        ? scrolled
        : Math.max(1, Math.round(progress * TIMING_MARKS));

    return (
        <div
            aria-hidden
            className="fixed inset-y-0 left-0 z-20 flex w-7 flex-col justify-between border-r border-(--ink-line) bg-white py-5 sm:w-12 sm:py-8"
        >
            {Array.from({ length: TIMING_MARKS }, (_, index) => (
                <span
                    key={index}
                    className={`timing-mark ml-2 block h-1.5 sm:ml-4 ${
                        index < filled
                            ? 'w-3 bg-(--graphite) sm:w-5'
                            : 'w-2 bg-(--ink-line) sm:w-3'
                    }`}
                />
            ))}
        </div>
    );
}

/** "SIMAS" set in Archivo's expanded width, the only expanded setting. */
export function Wordmark({ className = 'text-2xl' }: { className?: string }) {
    return (
        <span
            className={`font-black tracking-[-0.02em] [font-stretch:118%] ${className}`}
        >
            SIMAS
        </span>
    );
}
