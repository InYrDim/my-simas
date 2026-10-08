<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * The front door. The app shell decides only WHERE a visitor belongs —
 * the pages themselves live in their modules.
 *
 * - console host                  -> the provider console
 * - school session (tenant+user)  -> the school's landing (Core)
 * - applicant session             -> the applicant's onboarding (Platform)
 * - remembered school, no user    -> the school login
 * - anyone else                   -> the public landing page (Platform)
 *
 * Schools share the central host and are told apart by the school
 * code, so a visitor who already carries one is sent straight to log in.
 */
final class EntryController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenantContext): RedirectResponse|Response
    {
        if (strtolower($request->getHost()) === config('tenancy.console_domain')) {
            return Auth::guard('provider')->check()
                ? redirect()->route('platform.home')
                : redirect()->route('platform.login');
        }

        if ($request->user() !== null) {
            return redirect()->route('home');
        }

        if (Auth::guard('applicant')->check()) {
            return redirect()->route('applicant.home');
        }

        if ($tenantContext->id() !== null) {
            return redirect()->route('login');
        }

        return Inertia::render('Platform/Landing', [
            'trialDays' => (int) config('billing.trial_days', 14),
        ]);
    }
}
