<?php

namespace Modules\Platform\App\Http\Controllers\Applicant;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Contracts\SchoolSessionOpener;
use Modules\Platform\App\Domain\Models\Applicant;

/**
 * Applicant session auth via the dedicated 'applicant' guard — separate
 * from school logins ('web') and the provider console ('provider').
 *
 * Once an application is approved the applicant is a school admin:
 * the same form then opens the SCHOOL session through
 * SchoolSessionOpener (implemented by Identity) and no applicant
 * session is created.
 *
 * Rate limiting mirrors the provider login: central buckets keyed
 * (applicant:{email}:{ip}). Every failure answers with the same generic
 * error, so the form cannot be used to probe which emails exist.
 */
final class SessionController
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
        private readonly SchoolSessionOpener $opener,
    ) {}

    public function create(): Response|RedirectResponse
    {
        if (Auth::guard('applicant')->check()) {
            return redirect()->route('applicant.home');
        }

        return Inertia::render('Platform/Applicant/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = sprintf(
            'applicant:%s:%s',
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

        $credentials['email'] = mb_strtolower(trim($credentials['email']));

        // An approved applicant is a school admin now: their password
        // moved to the school account at approval, so the credentials
        // are checked there and the SCHOOL session is opened.
        $approved = Applicant::query()
            ->where('email', $credentials['email'])
            ->whereNotNull('tenant_id')
            ->first();

        if ($approved !== null) {
            if (! $this->opener->attempt($approved->tenant_id, $credentials['email'], $credentials['password'], $request->boolean('remember'))) {
                RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

                throw ValidationException::withMessages([
                    'email' => __('auth.failed'),
                ]);
            }

            RateLimiter::clear($throttleKey);

            $request->session()->regenerate();

            // The front door sends a school session to its landing page.
            return redirect()->to('/');
        }

        if (! Auth::guard('applicant')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        return redirect()->intended(route('applicant.home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('applicant')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('applicant.login');
    }
}
