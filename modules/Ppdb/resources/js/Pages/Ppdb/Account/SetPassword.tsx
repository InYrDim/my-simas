import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { Button } from '@shared/components/ui/button';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';

import PortalPage from '../../../Components/PortalPage';

/**
 * Set a new password from an emailed link. `action` is the signed URL the
 * page was opened with — the form posts back to it, signature included.
 */
export default function SetPassword({
    email,
    action,
}: {
    email: string;
    action: string;
}) {
    const form = useForm({
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(action, {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <PortalPage
            title="Buat kata sandi baru"
            description={`Buat kata sandi baru untuk ${email}.`}
        >
            <form onSubmit={submit} noValidate>
                <FieldGroup>
                    <Field data-invalid={!!form.errors.password}>
                        <FieldLabel htmlFor="password">Kata sandi</FieldLabel>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            autoComplete="new-password"
                            autoFocus
                            required
                            value={form.data.password}
                            aria-invalid={!!form.errors.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                        />
                        <FieldError>{form.errors.password}</FieldError>
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="password_confirmation">
                            Ulangi kata sandi
                        </FieldLabel>
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={form.data.password_confirmation}
                            onChange={(event) =>
                                form.setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Menyimpan...' : 'Simpan kata sandi'}
                    </Button>
                </FieldGroup>
            </form>
        </PortalPage>
    );
}
