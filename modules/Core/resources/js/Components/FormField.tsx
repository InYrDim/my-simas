import type { ComponentProps } from 'react';

import { Field, FieldDescription, FieldLabel } from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@shared/components/ui/select';

/** Label + Input (uncontrolled) for the mockup forms. */
export function InputField({
    label,
    id,
    hint,
    ...props
}: { label: string; id: string; hint?: string } & Omit<
    ComponentProps<typeof Input>,
    'id'
>) {
    return (
        <Field>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <Input id={id} name={id} {...props} />
            {hint !== undefined && <FieldDescription>{hint}</FieldDescription>}
        </Field>
    );
}

/** Label + shared Select (uncontrolled) for the mockup forms. */
export function SelectField({
    label,
    id,
    options,
    defaultValue,
}: {
    label: string;
    id: string;
    options: string[] | { value: string; label: string }[];
    defaultValue?: string;
}) {
    const items = options.map((option) =>
        typeof option === 'string' ? { value: option, label: option } : option,
    );

    return (
        <Field>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <Select name={id} defaultValue={defaultValue ?? items[0]?.value}>
                <SelectTrigger id={id}>
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
        </Field>
    );
}
