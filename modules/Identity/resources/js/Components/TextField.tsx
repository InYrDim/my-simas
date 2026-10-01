import type { ComponentProps } from 'react';

import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';

/** Label + Input + hint/error, built from the shared Field primitives. */
export default function TextField({
    label,
    id,
    error,
    hint,
    ...props
}: { label: string; id: string; error?: string; hint?: string } & Omit<
    ComponentProps<typeof Input>,
    'id'
>) {
    return (
        <Field data-invalid={error ? true : undefined}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <Input
                id={id}
                name={id}
                aria-invalid={error ? true : undefined}
                {...props}
            />
            {error ? (
                <FieldError>{error}</FieldError>
            ) : (
                hint && <FieldDescription>{hint}</FieldDescription>
            )}
        </Field>
    );
}
