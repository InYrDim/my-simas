import { Field, FieldError, FieldLabel } from '@shared/components/ui/field';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@shared/components/ui/select';

const NO_ROLE = 'none';

/**
 * Optional single-role picker for the create/invite forms. The empty
 * choice is a sentinel because a Select item cannot carry an empty value.
 */
export default function RoleSelectField({
    roleLabels,
    value,
    error,
    onChange,
}: {
    roleLabels: Record<string, string>;
    value: string;
    error?: string;
    onChange: (role: string) => void;
}) {
    return (
        <Field data-invalid={error ? true : undefined}>
            <FieldLabel htmlFor="role">Peran (opsional)</FieldLabel>

            <Select
                value={value === '' ? NO_ROLE : value}
                onValueChange={(next) => onChange(next === NO_ROLE ? '' : next)}
            >
                <SelectTrigger
                    id="role"
                    aria-invalid={error ? true : undefined}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NO_ROLE}>— tanpa peran —</SelectItem>
                    {Object.entries(roleLabels).map(([name, label]) => (
                        <SelectItem key={name} value={name}>
                            {label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
}
