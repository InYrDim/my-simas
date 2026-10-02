<?php

namespace Modules\Core\App\Infrastructure\Whatsapp;

use Modules\Core\App\Contracts\DTOs\NoticeKind;
use Modules\Core\App\Contracts\NoticeRegistry;
use Modules\Platform\App\Contracts\TenantModules;

/**
 * In-memory registry of notice kinds, populated by each module's service
 * provider at boot. The read side is Core's own and filters by active
 * module on every call.
 */
final class DefaultNoticeRegistry implements NoticeRegistry
{
    /**
     * @var array<string, array{module: string, kind: NoticeKind}> keyed by kind key
     */
    private array $kinds = [];

    public function __construct(
        private readonly TenantModules $tenantModules,
    ) {}

    public function register(string $module, NoticeKind $kind): void
    {
        $this->kinds[$kind->key] = ['module' => $module, 'kind' => $kind];
    }

    /**
     * The kinds the current school can use, in registration order.
     *
     * @return list<NoticeKind>
     */
    public function available(): array
    {
        $available = [];

        foreach ($this->kinds as $entry) {
            if ($this->tenantModules->isEnabled($entry['module'])) {
                $available[] = $entry['kind'];
            }
        }

        return $available;
    }

    /**
     * The kind with this key, when its module is active for the current
     * tenant.
     */
    public function find(string $key): ?NoticeKind
    {
        $entry = $this->kinds[$key] ?? null;

        return $entry !== null && $this->tenantModules->isEnabled($entry['module']) ? $entry['kind'] : null;
    }

    /**
     * Announced kinds nobody has registered yet ("Segera hadir").
     *
     * @return list<array{key: string, title: string, description: string, recipient: string}>
     */
    public function upcoming(): array
    {
        /** @var list<array{key: string, title: string, description: string, recipient: string}> $announced */
        $announced = config('notices.upcoming', []);

        return array_values(array_filter(
            $announced,
            fn (array $kind): bool => ! array_key_exists($kind['key'], $this->kinds),
        ));
    }
}
