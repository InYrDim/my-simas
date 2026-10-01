<?php

namespace Modules\Identity\App\Http\Controllers\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * "Reset password" — consumes a tenant-scoped token and sets the new
 * password. Runs on the tenant host (token rows are scoped by the
 * ambient context; a token from tenant A cannot be presented here for
 * tenant B's account even when the email matches).
 */
final class NewPasswordController
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Identity/Auth/ResetPassword', [
            'email' => (string) $request->query('email', ''),
            'token' => (string) $request->query('token', ''),
        ]);
    }

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

        $status = Password::broker()->reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(10),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        // Log the user in on their own host: the token proved identity.
        // (Only reachable for ACTIVE users — sendResetLink skips the
        // deactivated, so no token exists for them to consume.)
        $user = User::query()->where('email', $validated['email'])->firstOrFail();

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('home');
    }
}
