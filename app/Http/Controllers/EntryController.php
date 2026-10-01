<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The front door. The app shell decides only WHERE a visitor belongs —
 * the pages themselves live in their modules.
 *
 * - console host                  -> the provider console
 * - school session (tenant+user)  -> the school's landing (Core)
 * - applicant session             -> the applicant's onboarding (Platform)
 * - anyone else                   -> the school login
 *
 * Schools share the central host and are told apart by the school
 * code, so a visitor without a session simply logs in.
 */
final class EntryController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if (strtolower($request->getHost()) === config('tenancy.console_domain')) {
            return Auth::guard('provider')->check()
                ? redirect()->route('platform.home')
                : redirect()->route('platform.login');
        }

        if ($request->user() !== null) {
            return redirect()->route('home');
        }

        return Auth::guard('applicant')->check()
            ? redirect()->route('applicant.home')
            : redirect()->route('login');
    }
}
