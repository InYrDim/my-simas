import { Link, useForm } from '@inertiajs/react';

import { store } from '@/actions/Modules/Ppdb/App/Http/Controllers/ApplicantController';
import { applicants } from '@/routes/ppdb';
import { EmptyState, OptionSelect, Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Button } from '@shared/components/ui/button';
import { Field, FieldError, FieldLabel } from '@shared/components/ui/field';

import FormRenderer, {
    formBinding,
    initialAnswers,
    type ApplicantData,
    type FormFieldDef,
} from '../../Components/FormRenderer';
import PpdbPage from '../../Components/PpdbPage';

type Option = { value: string; label: string };

/** Tambah pendaftar: the committee enters someone who came in person. */
export default function ApplicantForm({
    period,
    waves,
    paths,
    fields,
}: {
    period: { id: number; name: string } | null;
    waves: Option[];
    paths: Option[];
    fields: FormFieldDef[];
}) {
    const form = useForm<ApplicantData>({
        wave_id: waves.length === 1 ? waves[0].value : '',
        path_id: '',
        name: '',
        gender: '',
        birth_place: '',
        birth_date: '',
        nisn: '',
        origin_school: '',
        address: '',
        guardian_name: '',
        guardian_phone: '',
        answers: initialAnswers(fields, {}),
    });
    const errors: Partial<Record<string, string>> = form.errors;
    const binding = formBinding(form.data, errors, (key, value) =>
        form.setData(key as keyof ApplicantData, value as never),
    );

    if (period === null || waves.length === 0) {
        return (
            <PpdbPage title="Tambah pendaftar" width="max-w-3xl">
                <EmptyState>
                    {period === null
                        ? 'Belum ada periode PPDB yang berjalan, jadi belum ada yang bisa didaftarkan.'
                        : 'Periode ini belum punya gelombang pendaftaran.'}{' '}
                    <Link
                        href={applicants.url()}
                        className="underline underline-offset-4"
                    >
                        Kembali ke daftar pendaftar
                    </Link>
                </EmptyState>
            </PpdbPage>
        );
    }

    return (
        <PpdbPage
            title="Tambah pendaftar"
            description={`Pendaftar baru untuk ${period.name}.`}
            width="max-w-3xl"
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(store.url());
                }}
            >
                {errors.period !== undefined && (
                    <Alert variant="destructive" className="mb-6">
                        <AlertDescription>{errors.period}</AlertDescription>
                    </Alert>
                )}
                <Panel>
                    <FormRenderer
                        fields={fields}
                        paths={paths}
                        {...binding}
                        before={
                            <Field data-invalid={errors.wave_id !== undefined}>
                                <FieldLabel>Gelombang</FieldLabel>
                                <OptionSelect
                                    label="Gelombang"
                                    placeholder="Pilih gelombang"
                                    value={form.data.wave_id}
                                    onChange={(value) =>
                                        form.setData('wave_id', value)
                                    }
                                    options={waves}
                                />
                                {errors.wave_id !== undefined && (
                                    <FieldError>{errors.wave_id}</FieldError>
                                )}
                            </Field>
                        }
                    />
                </Panel>
                <div className="mt-6 flex gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Simpan pendaftar
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={applicants.url()}>Batal</Link>
                    </Button>
                </div>
            </form>
        </PpdbPage>
    );
}
