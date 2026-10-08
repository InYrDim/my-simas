import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { CircleAlertIcon } from 'lucide-react';
import type {
    ButtonHTMLAttributes,
    InputHTMLAttributes,
    ReactNode,
} from 'react';
import { useState } from 'react';

import { cn } from '../../lib/utils';

type LembarInputProps = {
    label: string;
    error?: string;
    hint?: ReactNode;
    /** One character per printed box (the school code). */
    comb?: boolean;
} & Omit<InputHTMLAttributes<HTMLInputElement>, 'className'>;

/**
 * A field printed on the sheet: cobalt caps label above, the box, then a
 * hint or — in the teacher's red pen — what needs correcting. A password
 * box carries a "Lihat" switch, worth having on a phone keyboard.
 */
export function LembarInput({
    label,
    error,
    hint,
    comb = false,
    id,
    type = 'text',
    ...props
}: LembarInputProps) {
    const inputId =
        id ?? props.name ?? label.toLowerCase().replace(/\s+/g, '-');
    const noteId = `${inputId}-note`;
    const isPassword = type === 'password';
    const [revealed, setRevealed] = useState(false);
    const note = error ?? hint;

    return (
        <div className="flex flex-col gap-2">
            <label
                htmlFor={inputId}
                className="text-xs font-bold tracking-[0.1em] text-(--ink-deep) uppercase"
            >
                {label}
            </label>

            <div className="relative">
                <input
                    id={inputId}
                    type={isPassword && revealed ? 'text' : type}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={note ? noteId : undefined}
                    className={cn(
                        'lembar-box block h-12 w-full rounded-[2px] border bg-white px-3 text-base text-(--graphite) transition-colors',
                        error
                            ? 'border-(--correction)'
                            : 'border-(--ink) hover:border-(--ink-deep)',
                        comb && 'comb-input font-code text-xl font-medium',
                        isPassword && 'pr-24',
                    )}
                    {...(comb
                        ? { autoCapitalize: 'none', spellCheck: false }
                        : {})}
                    {...props}
                />

                {isPassword && (
                    <button
                        type="button"
                        aria-controls={inputId}
                        aria-pressed={revealed}
                        onClick={() => setRevealed((current) => !current)}
                        className="lembar-switch absolute inset-y-0 right-0 flex min-w-11 items-center px-3 text-sm font-semibold text-(--ink-deep) underline decoration-(--ink-line) underline-offset-4 transition-colors hover:text-(--graphite) hover:decoration-(--ink)"
                    >
                        {revealed ? 'Tutup' : 'Lihat'}
                    </button>
                )}
            </div>

            {error ? (
                <p
                    id={noteId}
                    className="flex items-start gap-1.5 text-sm leading-snug font-semibold text-(--correction)"
                >
                    <CircleAlertIcon
                        aria-hidden
                        className="mt-px size-4 shrink-0"
                    />
                    {error}
                </p>
            ) : hint ? (
                <p id={noteId} className="text-sm leading-snug text-(--pencil)">
                    {hint}
                </p>
            ) : null}
        </div>
    );
}

/** A yes/no answer marked as a bubble, filled in pencil when chosen. */
export function LembarCheck({
    label,
    checked,
    onChange,
    name,
}: {
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    name: string;
}) {
    return (
        <label className="inline-flex min-h-11 cursor-pointer items-center gap-3 text-[15px] font-medium select-none">
            <input
                type="checkbox"
                name={name}
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
                className="peer sr-only"
            />
            <span className="relative inline-grid size-5 shrink-0 place-items-center rounded-full border border-(--ink) peer-focus-visible:outline-2 peer-focus-visible:outline-offset-3 peer-focus-visible:outline-(--graphite)">
                {checked && (
                    <span className="pencil-fill absolute inset-[-1px] rounded-full bg-(--graphite)" />
                )}
            </span>
            {label}
        </label>
    );
}

/**
 * The page's one action in graphite, or a quiet paper button beside it
 * for a way out ("Keluar").
 */
export function LembarButton({
    quiet = false,
    className,
    ...props
}: { quiet?: boolean } & ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            className={cn(
                'inline-flex h-12 items-center justify-center rounded-[2px] px-6 text-[15px] font-semibold transition-colors active:translate-y-px disabled:cursor-not-allowed disabled:opacity-60 disabled:active:translate-y-0',
                quiet
                    ? 'border border-(--ink) bg-white text-(--graphite) hover:bg-(--ink-tint)'
                    : 'bg-(--graphite) text-white hover:bg-(--ink-deep)',
                className,
            )}
            {...props}
        />
    );
}

/** A quiet link: graphite words over a cobalt underline. */
export function LembarLink({ className, ...props }: InertiaLinkProps) {
    return (
        <Link
            className={cn(
                'inline-flex min-h-11 items-center text-[15px] font-semibold underline decoration-(--ink) decoration-2 underline-offset-[6px] transition-colors hover:text-(--ink-deep)',
                className,
            )}
            {...props}
        />
    );
}

/** A fact that just happened, printed in the sheet's tint. */
export function LembarNotice({ children }: { children: ReactNode }) {
    return (
        <p
            role="status"
            className="border border-(--ink) bg-(--ink-tint) px-4 py-3 text-[15px] leading-relaxed"
        >
            {children}
        </p>
    );
}
