<?php

namespace Modules\Identity\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\App\Domain\Actions\CreateUser;
use Modules\Identity\App\Domain\Actions\DeactivateUser;
use Modules\Identity\App\Domain\Actions\InviteUser;
use Modules\Identity\App\Domain\Actions\ReactivateUser;
use Modules\Identity\App\Domain\Actions\SendResetLink;
use Modules\Identity\App\Domain\Exceptions\DeactivationNotAllowedException;
use Modules\Identity\App\Domain\Exceptions\InvitationNotAllowedException;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * School-admin user management (Fase 2 Stage 9). Every action goes
 * through the UserPolicy (permission gate + tenant re-assert) and the
 * Domain actions, so the controller only translates HTTP ⇄ domain:
 * the same rules are testable without HTTP.
 *
 * Route model binding is NOT used for targets: the id is bound inside
 * the tenant-scoped query manually, so an id belonging to another
 * tenant 404s (combined with the policy's same-tenant re-assert).
 */
final class UsersManagementController
{
    /**
     * Invitation attempts per bucket (tenant-keyed, like login —
     * middleware ordering cannot guarantee tenant context).
     */
    private const INVITE_MAX_ATTEMPTS = 5;

    private const INVITE_DECAY_SECONDS = 300;

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * User list: name, email, roles (labels), status.
     */
    public function index(): Response
    {
        $this->authorize('viewAny');

        $roleLabels = $this->roleLabels();

        $users = User::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->tenantRoleNames(),
                'roleLabels' => collect($user->tenantRoleNames())
                    ->map(fn (string $name): string => $roleLabels[$name] ?? $name)
                    ->values()
                    ->all(),
                'isActive' => $user->isActive(),
                'hasPassword' => $user->password !== null,
                'emailVerifiedAt' => $user->email_verified_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('Identity/Users/Index', [
            'users' => $users,
            'roleLabels' => $roleLabels,
        ]);
    }

    /**
     * Direct-create form.
     */
    public function create(): Response
    {
        $this->authorize('create');

        return Inertia::render('Identity/Users/Create', [
            'roleLabels' => $this->roleLabels(),
        ]);
    }

    /**
     * Direct-create: verified email (trusted admin input), optional
     * initial role.
     */
    public function store(Request $request, CreateUser $action): RedirectResponse
    {
        $this->authorize('create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                // unique(tenant_id, email): the same email may live in
                // another tenant — only THIS tenant's rows conflict.
                Rule::unique('users', 'email')
                    ->where('tenant_id', (string) $this->context->id()),
            ],
            'password' => ['required', 'string', Rules\Password::defaults()],
            'role' => ['nullable', 'string', Rule::in(array_keys($this->roleLabels()))],
        ]);

        $action->handle(
            ['name' => $validated['name'], 'email' => $validated['email'], 'password' => $validated['password']],
            $validated['role'] ?? null,
        );

        return redirect()
            ->route('identity.users.index')
            ->with('status', 'Pengguna berhasil dibuat.');
    }

    /**
     * Invitation form (Stage 10): the email path for password-null
     * accounts. Gated by identity.users.create — same permission as
     * direct-create (locked decision: no separate invite permission).
     */
    public function invite(): Response
    {
        $this->authorize('create');

        return Inertia::render('Identity/Users/Invite', [
            'roleLabels' => $this->roleLabels(),
        ]);
    }

    /**
     * Send the invitation: create-or-reinvite (password null) + token
     * + queued SetPasswordMail (the Stage 8 machinery). Rate limited
     * tenant-keyed; an active account is refused via the domain
     * exception (surfaced as a validation error on the email field).
     */
    public function storeInvite(Request $request, InviteUser $action): RedirectResponse
    {
        $this->authorize('create');

        $tenantId = $this->context->id();

        if ($tenantId === null) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'role' => ['nullable', 'string', Rule::in(array_keys($this->roleLabels()))],
        ]);

        $throttleKey = sprintf(
            'invite:%s:%s:%s',
            $tenantId,
            strtolower($validated['email']),
            $request->ip(),
        );

        if (RateLimiter::tooManyAttempts($throttleKey, self::INVITE_MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                    'minutes' => (int) ceil(RateLimiter::availableIn($throttleKey) / 60),
                ]),
            ]);
        }

        RateLimiter::hit($throttleKey, self::INVITE_DECAY_SECONDS);

        try {
            $action->handle(
                ['name' => $validated['name'], 'email' => $validated['email']],
                $validated['role'] ?? null,
            );
        } catch (InvitationNotAllowedException $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        return redirect()
            ->route('identity.users.index')
            ->with('status', 'Undangan terkirim — tautan aktivasi dikirim ke email tersebut.');
    }

    /**
     * Edit form: profile, roles, activation, reset link. Includes the
     * anti-lockout flags so the UI can disable the deactivate button
     * for self and the last active admin (invariants from Stage 3 —
     * the action refuses them regardless).
     */
    public function edit(string $userId): Response
    {
        $target = $this->findTenantUserOrFail($userId);

        $actor = $this->authorize('update', $target);

        return Inertia::render('Identity/Users/Edit', [
            'user' => [
                'id' => (int) $target->id,
                'name' => $target->name,
                'email' => $target->email,
                'roleNames' => $target->tenantRoleNames(),
                'isActive' => $target->isActive(),
                'hasPassword' => $target->password !== null,
            ],
            'roleLabels' => $this->roleLabels(),
            'isSelf' => (int) $actor->id === (int) $target->id,
            'isLastActiveAdmin' => $this->isLastActiveAdmin($target),
        ]);
    }

    /**
     * Update name + role assign/remove (full sync via dropdown).
     */
    public function update(Request $request, string $userId): RedirectResponse
    {
        $target = $this->findTenantUserOrFail($userId);

        $this->authorize('update', $target);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::in(array_keys($this->roleLabels()))],
        ]);

        $target->forceFill(['name' => $validated['name']])->save();

        /** @var list<string> $desiredRoles */
        $desiredRoles = array_values((array) $validated['roles']);

        $current = collect($target->tenantRoleNames());
        $desired = collect($desiredRoles);

        foreach ($current->diff($desired) as $removedRole) {
            $target->removeTenantRole($removedRole);
        }

        foreach ($desired->diff($current) as $addedRole) {
            $target->assignTenantRole($addedRole);
        }

        return back()->with('status', 'Perubahan disimpan.');
    }

    /**
     * Deactivate (audit stamp; roles kept). The Domain action refuses
     * self-deactivation and removing the last active admin.
     */
    public function deactivate(Request $request, string $userId): RedirectResponse
    {
        $target = $this->findTenantUserOrFail($userId);

        $this->authorize('deactivate', $target);

        try {
            $actor = $request->user('web');

            if (! $actor instanceof User) {
                abort(403);
            }

            app(DeactivateUser::class)->handle($actor, $target);
        } catch (DeactivationNotAllowedException $e) {
            return back()->withErrors(['user' => $e->getMessage()]);
        }

        return back()->with('status', 'Akun dinonaktifkan.');
    }

    /**
     * Reactivate: access resumes (roles were never removed).
     */
    public function reactivate(string $userId): RedirectResponse
    {
        $target = $this->findTenantUserOrFail($userId);

        $this->authorize('reactivate', $target);

        app(ReactivateUser::class)->handle($target);

        return back()->with('status', 'Akun diaktifkan kembali.');
    }

    /**
     * Send a reset link through the Stage 4 tenant-scoped machinery.
     * Generic success either way — no account-state disclosure.
     */
    public function sendReset(string $userId): RedirectResponse
    {
        $target = $this->findTenantUserOrFail($userId);

        $this->authorize('sendReset', $target);

        app(SendResetLink::class)->handle($target);

        return back()->with('status', 'Jika akun memenuhi syarat, tautan reset telah dikirim.');
    }

    /**
     * Gate through the policy (request context).
     *
     * @return User The actor, for callers that need it.
     */
    private function authorize(string $ability, ?User $target = null): User
    {
        /** @var User $actor */
        $actor = request()->user();

        Gate::authorize($ability, $target ?? User::class);

        return $actor;
    }

    /**
     * Tenant-scoped target lookup: an id from another tenant 404s
     * (BelongsToTenant scope), even before the policy runs.
     */
    private function findTenantUserOrFail(string $userId): User
    {
        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        return $user;
    }

    /**
     * Machine name → label, for dropdowns and display.
     *
     * @return array<string, string>
     */
    private function roleLabels(): array
    {
        /** @var array<string, array{label: string, permissions: list<string>}> $roles */
        $roles = config('roles');

        return collect($roles)
            ->map(fn (array $definition): string => $definition['label'])
            ->all();
    }

    /**
     * Whether the target is the tenant's only ACTIVE admin (Stage 3
     * invariant, mirrored for the UI's disable logic).
     */
    private function isLastActiveAdmin(User $target): bool
    {
        if (! $target->hasTenantRole('admin-sekolah') || ! $target->isActive()) {
            return false;
        }

        $activeAdmins = User::query()
            ->where('deactivated_at', null)
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin-sekolah'))
            ->count();

        return $activeAdmins <= 1;
    }
}
