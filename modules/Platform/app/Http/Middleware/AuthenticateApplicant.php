<?php

namespace Modules\Platform\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Platform\App\Domain\Models\Applicant;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the applicant pages: a guest goes to the applicant login, and
 * — with the `verified` parameter — an applicant who has not verified
 * their email goes to the verification notice.
 *
 * Not `auth:applicant`: the app's guest redirect is host-aware and sends
 * guests to the SCHOOL login, which is the wrong door for an applicant.
 */
final class AuthenticateApplicant
{
    public function handle(Request $request, Closure $next, ?string $requirement = null): Response
    {
        /** @var Applicant|null $applicant */
        $applicant = Auth::guard('applicant')->user();

        if ($applicant === null) {
            return redirect()->guest(route('applicant.login'));
        }

        if ($requirement === 'verified' && ! $applicant->hasVerifiedEmail()) {
            return redirect()->route('applicant.verify.notice');
        }

        return $next($request);
    }
}
