import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { store } from '@/actions/Modules/Ppdb/App/Http/Controllers/Account/JoinSchoolController';
import { home } from '@/routes/ppdb/account';
import { Button } from '@shared/components/ui/button';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';

import PortalPage from '../../../Components/PortalPage';

/**
 * Join a school with its code — the same code the school's own people type
 * when they sign in. A link from the school fills the field.
 */
export default function Join({ code }: { code: string }) {
    const form = useForm({ code });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(store.url());
    }

    return (
        <PortalPage
            title="Gabung ke sekolah"
            description="Masukkan kode sekolah yang Anda terima dari sekolah untuk mendaftar di sana."
            footer={
                <Link href={home.url()} className="text-primary hover:underline">
                    Kembali ke halaman akun
                </Link>
            }
        >
            <form onSubmit={submit} noValidate>
                <FieldGroup>
                    <Field data-invalid={!!form.errors.code}>
                        <FieldLabel htmlFor="code">Kode sekolah</FieldLabel>
                        <Input
                            id="code"
                            name="code"
                            autoComplete="off"
                            autoCapitalize="none"
                            spellCheck={false}
                            autoFocus
                            required
                            className="font-mono"
                            value={form.data.code}
                            aria-invalid={!!form.errors.code}
                            onChange={(event) => form.setData('code', event.target.value)}
                        />
                        <FieldDescription>Satu akun hanya bisa mendaftar di satu sekolah.</FieldDescription>
                        <FieldError>{form.errors.code}</FieldError>
                    </Field>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Memeriksa...' : 'Gabung'}
                    </Button>
                </FieldGroup>
            </form>
        </PortalPage>
    );
}
