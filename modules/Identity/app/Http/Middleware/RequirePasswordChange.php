<?php

namespace Modules\Identity\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Identity\App\Domain\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account whose password was set by someone else (a student's birth
 * date, a password an admin read out) reaches nothing but the page that
 * changes it, and the logout. Read from the model's attribute on every
 * request, like deactivation.
 */
final class RequirePasswordChange
{
    private const ALLOWED_ROUTES = ['password.change', 'password.change.update', 'logout'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if ($user instanceof User && $user->must_change_password && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            if ($request->expectsJson()) {
                abort(403, 'Kata sandi harus diganti dulu.');
            }

            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
