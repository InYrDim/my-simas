import type { ReactNode } from 'react';

import { OptionSelect } from '@shared/components/page-parts';
import { Checkbox } from '@shared/components/ui/checkbox';
import { Field, FieldDescription, FieldError, FieldLabel } from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import { Label } from '@shared/components/ui/label';
import { Textarea } from '@shared/components/ui/textarea';

export type FieldType = 'builtin' | 'text' | 'paragraph' | 'number' | 'date' | 'select' | 'checkboxes' | 'file' | 'section';

/** One field of a period's registration form, as the pages receive it. */
export interface FormFieldDef {
    id: number | string;
    key: string | null;
    type: FieldType;
    label: string;
    help: string | null;
    required: boolean;
    archived: boolean;
    options: string[];
    rules: Record<string, unknown>;
}

/** A stored answer to a file field: its name and where to download it. */
export interface StoredFile {
    name: string;
    url: string;
}

/** What one field holds in a form: text, a list of choices, a file waiting to be sent, or nothing. */
export type FieldValue = string | string[] | File | null;

type Option = { value: string; label: string };

/** The ids the built-in fields have always had (tests and labels rely on them). */
const BUILTIN_IDS: Record<string, string> = {
    name: 'applicant-name',
    nisn: 'applicant-nisn',
    birth_place: 'applicant-birth-place',
    birth_date: 'applicant-birth-date',
    origin_school: 'applicant-origin',
    address: 'applicant-address',
    guardian_name: 'applicant-guardian',
    guardian_phone: 'applicant-phone',
};

const BUILTIN_TEXT_INPUT: Record<string, string> = {
    birth_date: 'date',
};

const ACCEPT: Record<string, string> = { pdf: 'application/pdf', image: 'image/jpeg,image/png' };

/** What the registration form of an applicant holds: the built-in fields by column, and the custom answers by field id. */
export type ApplicantData = {
    wave_id: string;
    path_id: string;
    name: string;
    gender: string;
    birth_place: string;
    birth_date: string;
    nisn: string;
    origin_school: string;
    address: string;
    guardian_name: string;
    guardian_phone: string;
    answers: Record<string, FieldValue>;
};

/** The answers a form starts with: what is stored, else nothing (an empty list for a checkboxes field). */
export function initialAnswers(fields: FormFieldDef[], stored: Record<string, string | string[]>): Record<string, FieldValue> {
    const answers: Record<string, FieldValue> = {};

    for (const field of fields) {
        if (field.key !== null || field.type === 'section') {
            continue;
        }

        answers[String(field.id)] = field.type === 'file' ? null : (stored[String(field.id)] ?? (field.type === 'checkboxes' ? [] : ''));
    }

    return answers;
}

/**
 * Connects the renderer to a page's form: the value of a field, writing it,
 * and its error — built-in fields live at the top of the form data, custom
 * ones in `answers`.
 */
export function formBinding(
    data: ApplicantData,
    errors: Partial<Record<string, string>>,
    setField: (key: string, value: unknown) => void,
) {
    return {
        getValue: (field: FormFieldDef): FieldValue =>
            field.key !== null
                ? ((data as unknown as Record<string, string>)[field.key] ?? '')
                : (data.answers[String(field.id)] ?? (field.type === 'checkboxes' ? [] : '')),
        setValue: (field: FormFieldDef, value: FieldValue) =>
            field.key !== null ? setField(field.key, value) : setField('answers', { ...data.answers, [String(field.id)]: value }),
        errorOf: (field: FormFieldDef): string | undefined => errors[field.key ?? `answers.${field.id}`],
    };
}

/** The slot a field's value lives in: the column of a built-in field, or `answers.<id>` of a custom one. */
export function slotOf(field: FormFieldDef): string {
    return field.key ?? `answers.${field.id}`;
}

function todayIso(): string {
    return new Date().toISOString().slice(0, 10);
}

function htmlId(field: FormFieldDef): string {
    return field.key !== null ? (BUILTIN_IDS[field.key] ?? `applicant-${field.key}`) : `field-${field.id}`;
}

function asText(value: FieldValue): string {
    return typeof value === 'string' ? value : '';
}

function asList(value: FieldValue): string[] {
    return Array.isArray(value) ? value : [];
}

/** The "(opsional)" mark of an input that may be left empty. */
function OptionalMark({ field }: { field: FormFieldDef }) {
    return field.required ? null : <span className="font-normal text-muted-foreground"> (opsional)</span>;
}

function Control({
    field,
    value,
    invalid,
    onChange,
    paths,
    stored,
    disabled,
}: {
    field: FormFieldDef;
    value: FieldValue;
    invalid: boolean;
    onChange: (value: FieldValue) => void;
    paths: Option[];
    stored?: StoredFile;
    disabled: boolean;
}) {
    const id = htmlId(field);

    if (field.key === 'path_id') {
        return <OptionSelect label={field.label} placeholder="Pilih jalur" value={asText(value)} onChange={onChange} options={paths} />;
    }

    if (field.key === 'gender') {
        return (
            <OptionSelect
                label={field.label}
                placeholder="Pilih jenis kelamin"
                value={asText(value)}
                onChange={onChange}
                options={[
                    { value: 'L', label: 'Laki-laki' },
                    { value: 'P', label: 'Perempuan' },
                ]}
            />
        );
    }

    if (field.key === 'address' || field.type === 'paragraph') {
        return (
            <Textarea
                id={id}
                value={asText(value)}
                maxLength={typeof field.rules.max_length === 'number' ? field.rules.max_length : undefined}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={invalid}
            />
        );
    }

    if (field.type === 'select') {
        return (
            <OptionSelect
                label={field.label}
                placeholder="Pilih salah satu"
                value={asText(value)}
                onChange={onChange}
                options={field.options.map((option) => ({ value: option, label: option }))}
            />
        );
    }

    if (field.type === 'checkboxes') {
        const chosen = asList(value);

        return (
            <div className="flex flex-col gap-2" role="group" aria-label={field.label}>
                {field.options.map((option, index) => {
                    const optionId = `${id}-${index}`;

                    return (
                        <div key={option} className="flex items-center gap-2">
                            <Checkbox
                                id={optionId}
                                checked={chosen.includes(option)}
                                onCheckedChange={(checked) =>
                                    onChange(checked === true ? [...chosen, option] : chosen.filter((item) => item !== option))
                                }
                            />
                            <Label htmlFor={optionId} className="font-normal">
                                {option}
                            </Label>
                        </div>
                    );
                })}
            </div>
        );
    }

    if (field.type === 'file') {
        const kinds = Array.isArray(field.rules.kinds) ? (field.rules.kinds as string[]) : ['pdf', 'image'];

        return (
            <div className="flex flex-col gap-2">
                {stored !== undefined && (
                    <p className="text-sm">
                        Berkas terkirim:{' '}
                        <a href={stored.url} className="underline underline-offset-4">
                            {stored.name}
                        </a>
                        . Pilih berkas baru untuk menggantinya.
                    </p>
                )}
                <Input
                    id={id}
                    type="file"
                    accept={kinds.map((kind) => ACCEPT[kind] ?? '').join(',')}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.files?.[0] ?? null)}
                    aria-invalid={invalid}
                />
            </div>
        );
    }

    let type = BUILTIN_TEXT_INPUT[field.key ?? ''] ?? 'text';
    let inputMode: 'numeric' | 'tel' | 'email' | undefined;
    let max: string | undefined;
    let min: string | undefined;
    let step: string | undefined;

    if (field.type === 'number') {
        type = 'number';
        step = 'any';
        min = typeof field.rules.min === 'number' ? String(field.rules.min) : undefined;
        max = typeof field.rules.max === 'number' ? String(field.rules.max) : undefined;
    } else if (field.type === 'date') {
        type = 'date';
        max = field.rules.allow_future === false ? todayIso() : undefined;
    } else if (field.key === 'birth_date') {
        max = todayIso();
    } else if (field.type === 'text') {
        if (field.rules.format === 'digits') {
            inputMode = 'numeric';
        } else if (field.rules.format === 'phone') {
            type = 'tel';
            inputMode = 'tel';
        } else if (field.rules.format === 'email') {
            type = 'email';
            inputMode = 'email';
        }
    }

    return (
        <Input
            id={id}
            type={type}
            inputMode={inputMode}
            min={min}
            max={max}
            step={step}
            maxLength={typeof field.rules.max_length === 'number' ? field.rules.max_length : undefined}
            value={asText(value)}
            onChange={(event) => onChange(event.target.value)}
            aria-invalid={invalid}
        />
    );
}

/**
 * The registration form of a period, drawn from its fields in order: the
 * built-in fields with their usual controls, the custom ones by type, and a
 * section as a heading. It holds no state — the page owns the values and
 * errors — so the same component serves the applicant, the committee, and
 * the builder's live preview. Archived fields are not drawn.
 */
export default function FormRenderer({
    fields,
    getValue,
    setValue,
    errorOf,
    paths,
    before,
    storedFiles,
    disabled = false,
}: {
    fields: FormFieldDef[];
    getValue: (field: FormFieldDef) => FieldValue;
    setValue: (field: FormFieldDef, value: FieldValue) => void;
    errorOf: (field: FormFieldDef) => string | undefined;
    paths: Option[];
    before?: ReactNode;
    storedFiles?: Record<string, StoredFile>;
    disabled?: boolean;
}) {
    return (
        <fieldset disabled={disabled} className="flex flex-col gap-5">
            {before}
            {fields
                .filter((field) => !field.archived)
                .map((field) => {
                    if (field.type === 'section') {
                        return (
                            <div key={field.id} className="border-t pt-5">
                                <h3 className="text-base font-semibold text-foreground">{field.label}</h3>
                                {field.help !== null && field.help !== '' && <p className="mt-1 text-sm text-muted-foreground">{field.help}</p>}
                            </div>
                        );
                    }

                    const error = errorOf(field);
                    const id = htmlId(field);
                    const isGroup = field.type === 'checkboxes';
                    const isSelect = field.key === 'path_id' || field.key === 'gender' || field.type === 'select';

                    return (
                        <Field key={field.id} data-invalid={error !== undefined}>
                            <FieldLabel htmlFor={isGroup || isSelect ? undefined : id}>
                                {field.label}
                                <OptionalMark field={field} />
                            </FieldLabel>
                            {field.help !== null && field.help !== '' && <FieldDescription>{field.help}</FieldDescription>}
                            <Control
                                field={field}
                                value={getValue(field)}
                                invalid={error !== undefined}
                                onChange={(value) => setValue(field, value)}
                                paths={paths}
                                stored={storedFiles?.[String(field.id)]}
                                disabled={disabled}
                            />
                            {error !== undefined && <FieldError>{error}</FieldError>}
                        </Field>
                    );
                })}
        </fieldset>
    );
}
