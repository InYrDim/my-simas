<?php

namespace Modules\Identity\App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\App\Domain\Models\User;

/**
 * A signed-in user changes their own password. An account whose password
 * was set by someone else (`must_change_password`) is sent here by the
 * RequirePasswordChange middleware and cannot go anywhere else first.
 */
final class ChangePasswordController
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Identity/Auth/ChangePassword', [
            'forced' => $this->user($request)->must_change_password,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Rules\Password::defaults()],
        ]);

        $this->user($request)->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        return redirect()->route('home')->with('status', 'Kata sandi diperbarui.');
    }

    private function user(Request $request): User
    {
        $user = $request->user('web');

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
