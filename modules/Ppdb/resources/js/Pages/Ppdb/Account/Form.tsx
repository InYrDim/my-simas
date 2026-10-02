import { Link, useForm } from '@inertiajs/react';

import { store, update } from '@/actions/Modules/Ppdb/App/Http/Controllers/Account/ApplicationController';
import { home } from '@/routes/ppdb/account';
import { Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Button } from '@shared/components/ui/button';

import FormRenderer, { formBinding, initialAnswers, type ApplicantData, type FormFieldDef, type StoredFile } from '../../../Components/FormRenderer';
import PortalPage from '../../../Components/PortalPage';

type Option = { value: string; label: string };

interface FormProps {
    period: string;
    paths: Option[];
    fields: FormFieldDef[];
    answers: Record<string, string | string[]>;
    files: Record<string, StoredFile>;
    applicant: {
        pathId: string;
        name: string;
        gender: string;
        birthPlace: string | null;
        birthDate: string | null;
        nisn: string | null;
        originSchool: string | null;
        address: string | null;
        guardianName: string | null;
        guardianPhone: string | null;
        note: string | null;
    } | null;
    defaultName: string;
}

/**
 * The applicant's registration form: filled once, and again only when the
 * committee asks for a correction (then the committee's note shows on top).
 */
export default function Form({ period, paths, fields, answers, files, applicant, defaultName }: FormProps) {
    const form = useForm<ApplicantData>({
        wave_id: '',
        path_id: applicant?.pathId ?? '',
        name: applicant?.name ?? defaultName,
        gender: applicant?.gender ?? '',
        birth_place: applicant?.birthPlace ?? '',
        birth_date: applicant?.birthDate ?? '',
        nisn: applicant?.nisn ?? '',
        origin_school: applicant?.originSchool ?? '',
        address: applicant?.address ?? '',
        guardian_name: applicant?.guardianName ?? '',
        guardian_phone: applicant?.guardianPhone ?? '',
        answers: initialAnswers(fields, answers),
    });
    const errors: Partial<Record<string, string>> = form.errors;
    const binding = formBinding(form.data, errors, (key, value) => form.setData(key as keyof ApplicantData, value as never));
    const correcting = applicant !== null;
    const refusal = errors.period ?? errors.application ?? errors.school;

    return (
        <PortalPage
            title={correcting ? 'Perbaiki data pendaftaran' : 'Formulir pendaftaran'}
            description={period}
            width="max-w-2xl"
        >
            <form
                className="flex flex-col gap-6"
                onSubmit={(event) => {
                    event.preventDefault();

                    // A file in the form makes Inertia send multipart, which only POST carries: a correction says it is a PUT.
                    if (correcting) {
                        form.transform((data) => ({ ...data, _method: 'put' }));
                        form.post(update.url());
                    } else {
                        form.transform((data) => data);
                        form.post(store.url());
                    }
                }}
            >
                {correcting && applicant.note !== null && (
                    <Alert>
                        <AlertDescription>
                            <strong>Catatan panitia:</strong> {applicant.note}
                        </AlertDescription>
                    </Alert>
                )}
                {refusal !== undefined && (
                    <Alert variant="destructive">
                        <AlertDescription>{refusal}</AlertDescription>
                    </Alert>
                )}

                <Panel>
                    <FormRenderer fields={fields} paths={paths} storedFiles={files} {...binding} />
                </Panel>

                <div className="flex gap-3">
                    <Button type="submit" disabled={form.processing}>
                        {correcting ? 'Kirim perbaikan' : 'Kirim pendaftaran'}
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={home.url()}>Batal</Link>
                    </Button>
                </div>
            </form>
        </PortalPage>
    );
}
