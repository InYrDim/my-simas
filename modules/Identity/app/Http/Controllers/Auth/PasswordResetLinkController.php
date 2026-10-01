<?php

namespace Modules\Identity\App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * "Forgot password" — creates a tenant-scoped reset token; the email
 * itself is sent by User::sendPasswordResetNotification() (a queued
 * Mailable — see the Fase 2 decision: no Notification machinery).
 *
 * The response is ALWAYS the same generic message: unknown email,
 * deactivated user, or a user without a password (not invited/never
 * set) all get it, so this endpoint cannot be used to enumerate
 * accounts or their state. Rate limiting lives here with the tenant id
 * in the bucket key (middleware ordering cannot guarantee context).
 */
final class PasswordResetLinkController
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 300;

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Identity/Auth/ForgotPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
        ]);

        $tenantId = $this->context->id();

        if ($tenantId === null) {
            // Unknown school code: same generic response as an unknown
            // email, so school codes cannot be probed here either.
            return back()->with('status', __('Jika email terdaftar, tautan reset telah dikirim.'));
        }

        $throttleKey = sprintf(
            'reset-link:%s:%s:%s',
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

        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        // The broker resolves the user through the tenant-scoped
        // provider query; eligibility (active, has a password) is
        // enforced in sendPasswordResetNotification — skipping the send
        // silently. Either way the response is identical.
        Password::broker()->sendResetLink(['email' => $validated['email']]);

        return back()->with('status', __('Jika email terdaftar, tautan reset telah dikirim.'));
    }
}
