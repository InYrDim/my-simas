<?php

namespace Modules\Identity\App\Http\Controllers\Console;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\App\Domain\Actions\DeactivateUser;
use Modules\Identity\App\Domain\Actions\InviteUser;
use Modules\Identity\App\Domain\Actions\ListSchoolAdmins;
use Modules\Identity\App\Domain\Actions\ReactivateUser;
use Modules\Identity\App\Domain\Actions\SendResetLink;
use Modules\Identity\App\Domain\Exceptions\DeactivationNotAllowedException;
use Modules\Identity\App\Domain\Exceptions\InvitationNotAllowedException;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantData;
use Modules\Platform\App\Contracts\TenantDirectory;

/**
 * Provider console → Pengguna: the school admins of every tenant. The
 * provider is not a school user, so every action targets an explicit
 * tenant and runs inside that tenant's context. Same actions as the
 * school-admin UI (invite, reset link, deactivate, reactivate); the
 * last-active-admin invariant still protects every tenant.
 */
final class SchoolAdminController
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly ListSchoolAdmins $admins,
        private readonly TenantDirectory $tenants,
        private readonly TenantContext $context,
        private readonly InviteUser $invite,
        private readonly SendResetLink $reset,
        private readonly DeactivateUser $deactivate,
        private readonly ReactivateUser $reactivate,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tenant' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', Rule::in(['active', 'invited', 'deactivated'])],
        ]);

        $rows = $this->admins->handle($filters['tenant'] ?? null)
            ->when($filters['q'] ?? null, fn ($rows, string $term) => $rows->filter(
                fn (array $row): bool => str_contains(mb_strtolower($row['name'].' '.$row['email']), mb_strtolower($term)),
            ))
            ->when($filters['status'] ?? null, fn ($rows, string $status) => $rows->where('status', $status))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();

        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Identity/Console/SchoolAdmins/Index', [
            'admins' => $paginator,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'tenant' => $filters['tenant'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'tenants' => collect($this->tenants->all())
                ->map(fn (TenantData $tenant): array => ['id' => $tenant->id, 'name' => $tenant->name])
                ->values(),
        ]);
    }

    /**
     * Invite (or re-invite) a school admin for one tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
        ]);

        $tenant = $this->tenant($data['tenant_id']);

        return $this->invitation($tenant, trim($data['name']), mb_strtolower(trim($data['email'])));
    }

    public function resendInvite(string $tenant, int $userId): RedirectResponse
    {
        $tenantData = $this->tenant($tenant);
        $user = $this->admin($tenantData->id, $userId);

        return $this->invitation($tenantData, $user->name, $user->email);
    }

    public function sendReset(string $tenant, int $userId): RedirectResponse
    {
        $user = $this->admin($this->tenant($tenant)->id, $userId);

        if (! $this->reset->handle($user)) {
            return back()->withErrors(['billing' => 'Akun ini belum aktif atau sudah dinonaktifkan; tautan atur ulang tidak dikirim.']);
        }

        return back()->with('status', "Tautan atur ulang kata sandi dikirim ke {$user->email}.");
    }

    public function deactivate(string $tenant, int $userId): RedirectResponse
    {
        $user = $this->admin($this->tenant($tenant)->id, $userId);

        try {
            $this->deactivate->handleAsProvider($user);
        } catch (DeactivationNotAllowedException) {
            return back()->withErrors(['billing' => 'Admin sekolah aktif terakhir tidak bisa dinonaktifkan.']);
        }

        return back()->with('status', "{$user->name} dinonaktifkan.");
    }

    public function reactivate(string $tenant, int $userId): RedirectResponse
    {
        $tenantData = $this->tenant($tenant);
        $user = $this->admin($tenantData->id, $userId);

        $this->context->run($tenantData->id, fn () => $this->reactivate->handle($user));

        return back()->with('status', "{$user->name} diaktifkan kembali.");
    }

    private function invitation(TenantData $tenant, string $name, string $email): RedirectResponse
    {
        try {
            $this->context->run($tenant->id, fn () => $this->invite->handle(
                ['name' => $name, 'email' => $email],
                ListSchoolAdmins::ROLE,
            ));
        } catch (InvitationNotAllowedException) {
            return back()->withErrors(['billing' => 'Akun dengan email ini sudah aktif; gunakan tautan atur ulang kata sandi.']);
        }

        return back()->with('status', "Undangan admin sekolah dikirim ke {$email} ({$tenant->name}).");
    }

    private function tenant(string $tenantId): TenantData
    {
        return $this->tenants->findMany([$tenantId])[$tenantId] ?? abort(404);
    }

    /**
     * The school admin with this id inside the tenant, or 404 (a user
     * without the admin role is not manageable from here).
     */
    private function admin(string $tenantId, int $userId): User
    {
        $user = $this->context->run($tenantId, fn (): ?User => User::query()
            ->whereKey($userId)
            ->whereHas('roles', fn ($query) => $query->where('name', ListSchoolAdmins::ROLE))
            ->first());

        return $user ?? abort(404);
    }
}
