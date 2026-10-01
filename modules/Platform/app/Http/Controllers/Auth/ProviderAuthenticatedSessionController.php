<?php

namespace Modules\Platform\App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Domain\Models\ProviderUser;

/**
 * Provider console session auth: the console host only (the routes are
 * bound to it), authenticated via the dedicated 'provider' guard
 * against `provider_users` — fully separate from tenant logins
 * (different guard, different users table, different hosts).
 *
 * Rate limiting mirrors Identity's tenant-keyed controller throttling,
 * but central buckets are keyed (provider:{email}:{ip}) — there is no
 * tenant dimension on a central host.
 */
final class ProviderAuthenticatedSessionController
{
    /**
     * Attempts allowed per bucket before throttling.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Bucket decay window in seconds.
     */
    private const DECAY_SECONDS = 60;

    /**
     * Display the provider login view.
     */
    public function create(): Response
    {
        return Inertia::render('Platform/Auth/ProviderLogin');
    }

    /**
     * Handle an incoming provider authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = sprintf(
            'provider:%s:%s',
            strtolower($credentials['email']),
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

        if (! Auth::guard('provider')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        /** @var ProviderUser $user */
        $user = Auth::guard('provider')->user();

        return redirect()->intended(route('platform.home'));
    }

    /**
     * Destroy the provider session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('provider')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
