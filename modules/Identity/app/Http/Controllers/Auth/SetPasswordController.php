<?php

namespace Modules\Identity\App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Set-password acceptance (Fase 2 Stage 8): the emailed link a newly
 * provisioned account uses to activate — set a password, verify the
 * email, and land logged-in on their own tenant host. Shares the
 * tenant-scoped token table with reset (the table is context-generic;
 * the page decides the effect — recorded decision).
 *
 * Needs a school context (tokens are scoped by ambient context): the
 * emailed link carries the school code, which seeds the session. Rate limiting lives here, keyed with the tenant
 * id — the same pattern as login (middleware throttle cannot rely on
 * tenant context). Deactivated accounts may not activate: the token is
 * refused and the row deleted (one-shot, like a consumed reset).
 */
final class SetPasswordController
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
     * The acceptance form (token + email come from the URL).
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Identity/Auth/SetPassword', [
            'email' => (string) $request->query('email', ''),
            'token' => (string) $request->query('token', ''),
        ]);
    }

    /**
     * Accept: validate, set password, verify email, auto-login.
     */
    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->context->id();

        if ($tenantId === null) {
            throw ValidationException::withMessages([
                'email' => __('Tautan tidak valid atau sudah kedaluwarsa.'),
            ]);
        }

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $throttleKey = sprintf(
            'set-password:%s:%s:%s',
            $tenantId,
            strtolower($validated['email']),
            $request->ip(),
        );

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                    'minutes' => (int) ceil(RateLimiter::availableIn($throttleKey) / 60),
                ]),
            ]);
        }

        $email = strtolower($validated['email']);

        // Resolution is tenant-scoped (BelongsToTenant). Deactivated
        // accounts can never activate: refuse BEFORE the broker, or a
        // still-valid provisioning token would set the password anyway
        // (the broker only knows tokens, not account status). Generic
        // message — status is not disclosed to token-less probes.
        $user = User::query()->where('email', $email)->first();

        if ($user !== null && ! $user->isActive()) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => [__('Tautan tidak valid atau sudah kedaluwarsa.')],
            ]);
        }

        $status = Password::broker()->reset(
            ['email' => $email, 'password' => $validated['password'], 'token' => $validated['token']],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'remember_token' => Str::random(10),
                ])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET || $user === null) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => [__('Tautan tidak valid atau sudah kedaluwarsa.')],
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('home');
    }
}
