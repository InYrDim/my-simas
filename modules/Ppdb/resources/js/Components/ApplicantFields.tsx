import { OptionSelect } from '@shared/components/page-parts';
import { Field, FieldError, FieldLabel } from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import { Textarea } from '@shared/components/ui/textarea';

/** What the form of an applicant holds; every field travels as text. */
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
};

type Option = { value: string; label: string };

/** What the period's form does with a field: asked and required, asked but optional, or not asked. */
export type FieldState = 'required' | 'optional' | 'off';

/** The state of each adjustable field, by field name; a field not listed is asked as before. */
export type FormFields = Partial<Record<keyof ApplicantData, FieldState>>;

/** The form a period has when the school never adjusted it. */
export const usualFormFields: FormFields = {
    birth_place: 'optional',
    birth_date: 'required',
    nisn: 'optional',
    origin_school: 'required',
    address: 'optional',
    guardian_name: 'required',
    guardian_phone: 'required',
};

function Text({
    id,
    label,
    value,
    error,
    onChange,
    type = 'text',
    optional = false,
}: {
    id: string;
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    type?: string;
    optional?: boolean;
}) {
    return (
        <Field data-invalid={error !== undefined}>
            <FieldLabel htmlFor={id}>
                {label}
                {optional && <span className="font-normal text-muted-foreground"> (opsional)</span>}
            </FieldLabel>
            <Input
                id={id}
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={error !== undefined}
            />
            {error !== undefined && <FieldError>{error}</FieldError>}
        </Field>
    );
}

/**
 * The fields of an applicant: used to enter one and to correct one. The
 * form that holds the values is the page's; this only draws the fields.
 * An applicant filling in their own form has no wave to choose (the open
 * wave is theirs), so the page leaves `waves` out. `fields` is the period's
 * form: a field it switches off is not drawn.
 */
export default function ApplicantFields({
    data,
    errors,
    onChange,
    waves,
    paths,
    fields,
    disabled = false,
}: {
    data: ApplicantData;
    errors: Partial<Record<string, string>>;
    onChange: (key: keyof ApplicantData, value: string) => void;
    waves?: Option[];
    paths: Option[];
    fields: FormFields;
    disabled?: boolean;
}) {
    const state = (key: keyof ApplicantData): FieldState => fields[key] ?? usualFormFields[key] ?? 'required';
    const asked = (key: keyof ApplicantData) => state(key) !== 'off';
    const optional = (key: keyof ApplicantData) => state(key) === 'optional';

    return (
        <fieldset disabled={disabled} className="flex flex-col gap-4">
            <div className={waves === undefined ? 'grid gap-4' : 'grid gap-4 sm:grid-cols-2'}>
                {waves !== undefined && (
                    <Field data-invalid={errors.wave_id !== undefined}>
                        <FieldLabel>Gelombang</FieldLabel>
                        <OptionSelect
                            label="Gelombang"
                            placeholder="Pilih gelombang"
                            value={data.wave_id}
                            onChange={(value) => onChange('wave_id', value)}
                            options={waves}
                        />
                        {errors.wave_id !== undefined && <FieldError>{errors.wave_id}</FieldError>}
                    </Field>
                )}
                <Field data-invalid={errors.path_id !== undefined}>
                    <FieldLabel>Jalur</FieldLabel>
                    <OptionSelect
                        label="Jalur"
                        placeholder="Pilih jalur"
                        value={data.path_id}
                        onChange={(value) => onChange('path_id', value)}
                        options={paths}
                    />
                    {errors.path_id !== undefined && <FieldError>{errors.path_id}</FieldError>}
                </Field>
            </div>

            <Text id="applicant-name" label="Nama lengkap" value={data.name} error={errors.name} onChange={(value) => onChange('name', value)} />

            <div className="grid gap-4 sm:grid-cols-2">
                <Field data-invalid={errors.gender !== undefined}>
                    <FieldLabel>Jenis kelamin</FieldLabel>
                    <OptionSelect
                        label="Jenis kelamin"
                        placeholder="Pilih jenis kelamin"
                        value={data.gender}
                        onChange={(value) => onChange('gender', value)}
                        options={[
                            { value: 'L', label: 'Laki-laki' },
                            { value: 'P', label: 'Perempuan' },
                        ]}
                    />
                    {errors.gender !== undefined && <FieldError>{errors.gender}</FieldError>}
                </Field>
                {asked('nisn') && (
                    <Text id="applicant-nisn" label="NISN" optional={optional('nisn')} value={data.nisn} error={errors.nisn} onChange={(value) => onChange('nisn', value)} />
                )}
            </div>

            {(asked('birth_place') || asked('birth_date')) && (
                <div className="grid gap-4 sm:grid-cols-2">
                    {asked('birth_place') && (
                        <Text id="applicant-birth-place" label="Tempat lahir" optional={optional('birth_place')} value={data.birth_place} error={errors.birth_place} onChange={(value) => onChange('birth_place', value)} />
                    )}
                    {asked('birth_date') && (
                        <Text id="applicant-birth-date" label="Tanggal lahir" type="date" optional={optional('birth_date')} value={data.birth_date} error={errors.birth_date} onChange={(value) => onChange('birth_date', value)} />
                    )}
                </div>
            )}

            {asked('origin_school') && (
                <Text id="applicant-origin" label="Asal sekolah" optional={optional('origin_school')} value={data.origin_school} error={errors.origin_school} onChange={(value) => onChange('origin_school', value)} />
            )}

            {asked('address') && (
                <Field data-invalid={errors.address !== undefined}>
                    <FieldLabel htmlFor="applicant-address">
                        Alamat
                        {optional('address') && <span className="font-normal text-muted-foreground"> (opsional)</span>}
                    </FieldLabel>
                    <Textarea
                        id="applicant-address"
                        value={data.address}
                        onChange={(event) => onChange('address', event.target.value)}
                        aria-invalid={errors.address !== undefined}
                    />
                    {errors.address !== undefined && <FieldError>{errors.address}</FieldError>}
                </Field>
            )}

            {(asked('guardian_name') || asked('guardian_phone')) && (
                <div className="grid gap-4 sm:grid-cols-2">
                    {asked('guardian_name') && (
                        <Text id="applicant-guardian" label="Nama wali" optional={optional('guardian_name')} value={data.guardian_name} error={errors.guardian_name} onChange={(value) => onChange('guardian_name', value)} />
                    )}
                    {asked('guardian_phone') && (
                        <Text id="applicant-phone" label="Telepon wali" optional={optional('guardian_phone')} value={data.guardian_phone} error={errors.guardian_phone} onChange={(value) => onChange('guardian_phone', value)} />
                    )}
                </div>
            )}
        </fieldset>
    );
}
