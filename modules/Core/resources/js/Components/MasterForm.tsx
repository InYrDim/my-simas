import { Form } from '@inertiajs/react';
import { createContext, useContext } from 'react';
import type { ReactNode } from 'react';

/** What a Wayfinder route definition gives us; the page picks the verb. */
export interface FormRoute {
    url: string;
    method: 'post' | 'put' | 'patch' | 'delete';
}

const ErrorsContext = createContext<Record<string, string>>({});

/** The server-side validation message for a field of the surrounding form. */
export function useFieldError(name: string): string | undefined {
    return useContext(ErrorsContext)[name];
}

/**
 * Submits to a controller route through Inertia's <Form>: the fields
 * inside (InputField, SelectField, ...) are plain named inputs, and
 * validation errors reach them through context.
 */
export default function MasterForm({
    route,
    className,
    onSuccess,
    children,
}: {
    route: FormRoute;
    className?: string;
    onSuccess?: () => void;
    children: ReactNode | ((state: { processing: boolean }) => ReactNode);
}) {
    return (
        <Form
            action={route.url}
            method={route.method}
            className={className}
            onSuccess={onSuccess}
            options={{ preserveScroll: true }}
        >
            {({ errors, processing }) => (
                <ErrorsContext.Provider value={errors}>
                    {typeof children === 'function'
                        ? children({ processing })
                        : children}
                </ErrorsContext.Provider>
            )}
        </Form>
    );
}
