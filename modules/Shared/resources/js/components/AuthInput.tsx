import type { InputHTMLAttributes } from 'react';

type AuthInputProps = {
    label: string;
    error?: string;
    hint?: string;
} & Omit<InputHTMLAttributes<HTMLInputElement>, 'className'>;

/**
 * Form field for auth surfaces: label above input, error below, WCAG
 * AA contrast in both shell tones. No placeholder-as-label.
 */
export function AuthInput({ label, error, hint, id, ...props }: AuthInputProps) {
    const inputId = id ?? props.name ?? label.toLowerCase().replace(/\s+/g, '-');
    const errorId = `${inputId}-error`;

    return (
        <div className="flex flex-col gap-2">
            <label
                htmlFor={inputId}
                className="text-sm font-medium text-zinc-700 dark:text-zinc-300"
            >
                {label}
            </label>

            <input
                id={inputId}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? errorId : undefined}
                className={`block w-full rounded-lg border bg-white px-3 py-2 text-sm text-zinc-900 transition-colors outline-none placeholder:text-zinc-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:bg-zinc-950 dark:text-zinc-100 dark:placeholder:text-zinc-500 ${
                    error ? 'border-red-500' : 'border-zinc-300 dark:border-zinc-700'
                }`}
                {...props}
            />

            {error ? (
                <p id={errorId} className="text-xs text-red-600 dark:text-red-400">
                    {error}
                </p>
            ) : hint ? (
                <p className="text-xs text-zinc-500 dark:text-zinc-500">{hint}</p>
            ) : null}
        </div>
    );
}
