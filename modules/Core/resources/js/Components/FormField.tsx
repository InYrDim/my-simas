import type { ComponentProps } from 'react';

import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@shared/components/ui/select';

import { useFieldError } from './MasterForm';

/** Label + Input (uncontrolled) with its server validation message. */
export function InputField({
    label,
    id,
    name,
    hint,
    ...props
}: { label: string; id: string; hint?: string } & Omit<
    ComponentProps<typeof Input>,
    'id'
>) {
    const error = useFieldError(name ?? id);

    return (
        <Field data-invalid={error !== undefined}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <Input
                id={id}
                name={name ?? id}
                aria-invalid={error !== undefined}
                {...props}
            />
            {hint !== undefined && <FieldDescription>{hint}</FieldDescription>}
            {error !== undefined && <FieldError>{error}</FieldError>}
        </Field>
    );
}

/** Label + shared Select (uncontrolled) with its server validation message. */
export function SelectField({
    label,
    id,
    name,
    options,
    defaultValue,
    optionalLabel,
    disabled,
    hint,
}: {
    label: string;
    id: string;
    name?: string;
    options: string[] | { value: string; label: string }[];
    defaultValue?: string | null;
    /** A disabled select is not submitted: send its value another way. */
    disabled?: boolean;
    hint?: string;
    /** Adds a "no choice" item (sent as `none`, which the server reads as null). */
    optionalLabel?: string;
}) {
    const error = useFieldError(name ?? id);
    const choices = options.map((option) =>
        typeof option === 'string' ? { value: option, label: option } : option,
    );
    const items =
        optionalLabel === undefined
            ? choices
            : [{ value: 'none', label: optionalLabel }, ...choices];

    return (
        <Field data-invalid={error !== undefined}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <Select
                name={name ?? id}
                disabled={disabled}
                defaultValue={
                    defaultValue !== undefined &&
                    defaultValue !== null &&
                    defaultValue !== ''
                        ? defaultValue
                        : items[0]?.value
                }
            >
                <SelectTrigger id={id} aria-invalid={error !== undefined}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {items.map((item) => (
                        <SelectItem key={item.value} value={item.value}>
                            {item.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {hint !== undefined && <FieldDescription>{hint}</FieldDescription>}
            {error !== undefined && <FieldError>{error}</FieldError>}
        </Field>
    );
}
