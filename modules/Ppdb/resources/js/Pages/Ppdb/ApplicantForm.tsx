import { Link, useForm } from '@inertiajs/react';

import { store } from '@/actions/Modules/Ppdb/App/Http/Controllers/ApplicantController';
import { applicants } from '@/routes/ppdb';
import { EmptyState, Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Button } from '@shared/components/ui/button';

import ApplicantFields, { type ApplicantData, type FormFields } from '../../Components/ApplicantFields';
import PpdbPage from '../../Components/PpdbPage';

type Option = { value: string; label: string };

/** Tambah pendaftar: the committee enters someone who came in person. */
export default function ApplicantForm({
    period,
    waves,
    paths,
    formFields,
}: {
    period: { id: number; name: string } | null;
    waves: Option[];
    paths: Option[];
    formFields: FormFields;
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
    });
    const errors: Partial<Record<string, string>> = form.errors;

    if (period === null || waves.length === 0) {
        return (
            <PpdbPage title="Tambah pendaftar" width="max-w-3xl">
                <EmptyState>
                    {period === null
                        ? 'Belum ada periode PPDB yang berjalan, jadi belum ada yang bisa didaftarkan.'
                        : 'Periode ini belum punya gelombang pendaftaran.'}{' '}
                    <Link href={applicants.url()} className="underline underline-offset-4">
                        Kembali ke daftar pendaftar
                    </Link>
                </EmptyState>
            </PpdbPage>
        );
    }

    return (
        <PpdbPage title="Tambah pendaftar" description={`Pendaftar baru untuk ${period.name}.`} width="max-w-3xl">
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
                    <ApplicantFields
                        data={form.data}
                        errors={errors}
                        onChange={(key, value) => form.setData(key, value)}
                        waves={waves}
                        paths={paths}
                        fields={formFields}
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
