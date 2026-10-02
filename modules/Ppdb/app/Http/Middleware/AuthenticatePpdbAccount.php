<?php

namespace Modules\Ppdb\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the applicant's own pages: a guest goes to the applicant login,
 * and — with the `verified` parameter — an account whose email is not
 * verified goes to the verification notice.
 *
 * Not `auth:ppdb`: the app's guest redirect is host-aware and would send an
 * applicant to the SCHOOL login, which is the wrong door.
 */
final class AuthenticatePpdbAccount
{
    public function handle(Request $request, Closure $next, ?string $requirement = null): Response
    {
        /** @var PpdbAccount|null $account */
        $account = Auth::guard('ppdb')->user();

        if ($account === null) {
            return redirect()->guest(route('ppdb.account.login'));
        }

        if ($requirement === 'verified' && ! $account->hasVerifiedEmail()) {
            return redirect()->route('ppdb.account.verify.notice');
        }

        return $next($request);
    }
}
