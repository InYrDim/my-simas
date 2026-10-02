import { Link, useForm } from '@inertiajs/react';

import { store, update } from '@/actions/Modules/Ppdb/App/Http/Controllers/Account/ApplicationController';
import { home } from '@/routes/ppdb/account';
import { Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Button } from '@shared/components/ui/button';

import ApplicantFields, { type ApplicantData, type FormFields } from '../../../Components/ApplicantFields';
import PortalPage from '../../../Components/PortalPage';

type Option = { value: string; label: string };

interface FormProps {
    period: string;
    paths: Option[];
    formFields: FormFields;
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
export default function Form({ period, paths, formFields, applicant, defaultName }: FormProps) {
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
    });
    const errors: Partial<Record<string, string>> = form.errors;
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

                    if (correcting) {
                        form.put(update.url());
                    } else {
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
                    <ApplicantFields data={form.data} errors={errors} onChange={(key, value) => form.setData(key, value)} paths={paths} fields={formFields} />
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
