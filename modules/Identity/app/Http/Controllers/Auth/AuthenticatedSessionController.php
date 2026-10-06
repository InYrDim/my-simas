<?php

namespace Modules\Identity\App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Tenant-scoped session authentication (Fase 1 scope: login/logout
 * only — no registration, no password reset; Fase 2).
 *
 * The ambient tenant context (set by ResolveTenant from the submitted
 * school code) scopes credential lookup: a user only ever logs into
 * the tenant their account belongs to. An unknown school code leaves
 * no context and is rejected before touching the users table.
 *
 * Rate limiting happens HERE (not in throttle: middleware) so the
 * bucket key can include the tenant id: middleware ordering cannot
 * guarantee the context is set when the limiter closure runs.
 *
 * Fase 2: deactivated accounts (deactivated_at set) are refused at
 * login with the generic credential error — same response as a wrong
 * password, so the endpoint cannot be used to enumerate account
 * states.
 */
final class AuthenticatedSessionController
{
    /**
     * Attempts allowed per bucket before throttling.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Bucket decay window in seconds.
     */
    private const DECAY_SECONDS = 60;

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * Display the login view (tenant context already resolved).
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Identity/Auth/Login', [
            'schoolLinked' => $request->route('school') !== null && $this->context->id() !== null,
        ]);
    }

    /**
     * Handle an incoming authentication request (Inertia form).
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'school' => ['nullable', 'string'],
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // One field, two identities: an email address, or the username
        // of an account without one (a student's NIS, a teacher's NIP).
        $login = trim($credentials['login']);
        $column = str_contains($login, '@') ? 'email' : 'username';

        $tenantId = $this->context->id();

        if ($tenantId === null) {
            // Unknown school code: the SAME generic error as a wrong
            // password, so the form cannot be used to probe which
            // school codes exist.
            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        $throttleKey = $this->throttleKey($tenantId, $login);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'login' => trans('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                    'minutes' => (int) ceil(RateLimiter::availableIn($throttleKey) / 60),
                ]),
            ]);
        }

        // Only the identity column and the password reach the provider:
        // it turns every other key into a WHERE column.
        if (! Auth::validate([$column => $login, 'password' => $credentials['password']])) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        // Re-check through the tenant scope: Auth::validate() resolves
        // the user via the scoped query, but assert it explicitly — a
        // VALID credential for tenant B's account must never
        // authenticate against tenant A. Deactivated accounts are
        // refused with the SAME generic error: the response must not
        // disclose whether an account exists or what state it is in.
        $user = User::query()
            ->where($column, $login)
            ->first();

        if ($user === null || $user->tenant_id !== $tenantId || ! $user->isActive()) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        // Fase 1 has no dashboard yet; the home route exists globally.
        return redirect()->intended(route('home'));
    }

    /**
     * Destroy an authenticated session (tenant-scoped logout).
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * The tenant-scoped throttle key: buckets never pool across
     * tenants, and one identity being brute-forced on tenant A never
     * locks out the same email or username on tenant B.
     */
    private function throttleKey(string $tenantId, string $login): string
    {
        return sprintf(
            'login:%s:%s:%s',
            $tenantId,
            strtolower($login),
            request()->ip(),
        );
    }
}
