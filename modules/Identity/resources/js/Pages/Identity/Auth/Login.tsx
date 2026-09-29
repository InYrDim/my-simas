import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { store as loginStore } from '@/actions/Modules/Identity/App/Http/Controllers/Auth/AuthenticatedSessionController';

/**
 * Tenant login (Fase 1). Rendered on a school subdomain; the tenant
 * context is resolved server-side from the host before this page is
 * ever served.
 */
export default function Login() {
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(loginStore.url(), {
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <form onSubmit={submit}>
            <label htmlFor="email">Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value={form.data.email}
                onChange={(event) => form.setData('email', event.target.value)}
                autoComplete="username"
                autoFocus
                required
            />
            {form.errors.email ? <p>{form.errors.email}</p> : null}

            <label htmlFor="password">Password</label>
            <input
                id="password"
                type="password"
                name="password"
                value={form.data.password}
                onChange={(event) => form.setData('password', event.target.value)}
                autoComplete="current-password"
                required
            />
            {form.errors.password ? <p>{form.errors.password}</p> : null}

            <label>
                <input
                    type="checkbox"
                    name="remember"
                    checked={form.data.remember}
                    onChange={(event) => form.setData('remember', event.target.checked)}
                />
                Remember me
            </label>

            <button type="submit" disabled={form.processing}>Log in</button>
        </form>
    );
}
