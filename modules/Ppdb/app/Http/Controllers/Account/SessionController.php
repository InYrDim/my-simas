<?php

namespace Modules\Ppdb\App\Http\Controllers\Account;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ppdb\App\Http\Requests\Account\LoginRequest;

/**
 * Sign in and out of an applicant's account through the dedicated `ppdb`
 * guard — separate from school logins (`web`), the provider console and
 * the school applicants.
 *
 * Rate limiting is central, keyed (email, ip): this page has no tenant.
 * Every failure answers with the same generic error, so the form cannot
 * be used to probe which emails have an account.
 */
final class SessionController
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function create(): Response|RedirectResponse
    {
        if (Auth::guard('ppdb')->check()) {
            return redirect()->route('ppdb.account.home');
        }

        return Inertia::render('Ppdb/Account/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $key = sprintf('ppdb-account:%s:%s', $credentials['email'], $request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
            ]);
        }

        if (! Auth::guard('ppdb')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages(['email' => 'Email atau kata sandi salah.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->to($this->destination($request));
    }

    /**
     * Where the applicant goes after signing in: the applicant page they
     * were sent from (the school's link to join, for one), or their own
     * page. The remembered address (`url.intended`) is shared by every guard
     * of the session, so one left by a school page — the applicant opened
     * `/ppdb` and was sent to the school login — is dropped, not followed.
     */
    private function destination(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');

        $path = is_string($intended) ? (string) parse_url($intended, PHP_URL_PATH) : '';

        if ($path === '/calon-siswa' || str_starts_with($path, '/calon-siswa/')) {
            return $intended;
        }

        return route('ppdb.account.home');
    }

    /**
     * Ends only the applicant's session: a school login in the same browser
     * is a different guard and stays.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('ppdb')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('ppdb.account.login');
    }
}
