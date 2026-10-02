<?php

namespace Modules\Identity\App\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantData;
use Modules\Platform\App\Contracts\TenantDirectory;

/**
 * Provider-console read model: the school admins (role admin-sekolah) of
 * every tenant, or of one. Users are tenant-scoped, so each tenant is
 * read inside its own context; the provider request has none of its own.
 */
final class ListSchoolAdmins
{
    public const ROLE = 'admin-sekolah';

    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantDirectory $tenants,
    ) {}

    /**
     * @return Collection<int, array{id: int, tenantId: string, tenantName: string, tenantSlug: string, tenantStatus: string, name: string, email: string|null, status: string}>
     */
    public function handle(?string $tenantId = null): Collection
    {
        $tenants = collect($tenantId !== null ? $this->tenants->findMany([$tenantId]) : $this->tenants->all());

        return $tenants
            ->flatMap(fn (TenantData $tenant): Collection => $this->adminsOf($tenant))
            ->sortBy([['tenantName', 'asc'], ['name', 'asc']])
            ->values();
    }

    /**
     * @return Collection<int, array{id: int, tenantId: string, tenantName: string, tenantSlug: string, tenantStatus: string, name: string, email: string|null, status: string}>
     */
    private function adminsOf(TenantData $tenant): Collection
    {
        /** @var Collection<int, User> $users */
        $users = $this->context->run(
            $tenant->id,
            fn (): Collection => User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', self::ROLE))
                ->orderBy('name')
                ->get(),
        );

        return $users->map(fn (User $user): array => [
            'id' => $user->id,
            'tenantId' => $tenant->id,
            'tenantName' => $tenant->name,
            'tenantSlug' => $tenant->slug,
            'tenantStatus' => $tenant->status->value,
            'name' => $user->name,
            'email' => $user->email,
            'status' => match (true) {
                $user->deactivated_at !== null => 'deactivated',
                $user->password === null => 'invited',
                default => 'active',
            },
        ]);
    }
}
