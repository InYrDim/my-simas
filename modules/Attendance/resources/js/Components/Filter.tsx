import type { ReactNode } from 'react';

import { Field, FieldLabel } from '@shared/components/ui/field';

/** A labelled filter control above a list (class, day, lesson, month). */
export default function Filter({
    label,
    htmlFor,
    children,
}: {
    label: string;
    htmlFor?: string;
    children: ReactNode;
}) {
    return (
        <Field className="w-full sm:w-56">
            <FieldLabel htmlFor={htmlFor}>{label}</FieldLabel>
            {children}
        </Field>
    );
}
