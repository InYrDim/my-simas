<?php

namespace Modules\Platform\App\Infrastructure\Usage;

use Modules\Platform\App\Contracts\DTOs\UsageLine;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Platform\App\Contracts\TenantUsage;
use Modules\Platform\App\Domain\Models\Subscription;

/**
 * Computes the registered meters inside the school's tenant context and
 * sets them against the limits of its subscription plan.
 */
final class DefaultTenantUsage implements TenantUsage
{
    public const PLATFORM_MODULE = 'platform';

    private const NEAR_RATIO = 0.9;

    public function __construct(
        private readonly DefaultUsageMeters $meters,
        private readonly TenantContext $context,
        private readonly TenantModules $modules,
    ) {}

    public function forTenant(string $tenantId): array
    {
        $plan = Subscription::query()->with('plan')->where('tenant_id', $tenantId)->first()?->plan;

        return $this->context->run($tenantId, function () use ($tenantId, $plan): array {
            $lines = [];

            foreach ($this->meters->all() as $meter) {
                if ($meter['module'] !== self::PLATFORM_MODULE && ! $this->modules->isEnabled($meter['module'], $tenantId)) {
                    continue;
                }

                $used = max(0, ($meter['counter'])());
                $limit = $plan?->limit($meter['limitKey']);

                $lines[] = new UsageLine($meter['key'], $meter['label'], $meter['unit'], $used, $limit, $this->stateOf($used, $limit));
            }

            return $lines;
        });
    }

    public function isOverLimit(string $key): bool
    {
        return $this->line($key)?->isOver() ?? false;
    }

    public function remaining(string $key): ?int
    {
        $line = $this->line($key);

        return $line === null || $line->limit === null ? null : max(0, $line->limit - $line->used);
    }

    private function line(string $key): ?UsageLine
    {
        $tenantId = $this->context->id();

        if ($tenantId === null) {
            return null;
        }

        foreach ($this->forTenant($tenantId) as $line) {
            if ($line->key === $key) {
                return $line;
            }
        }

        return null;
    }

    private function stateOf(int $used, ?int $limit): string
    {
        return match (true) {
            $limit === null => UsageLine::STATE_OK,
            $used > $limit => UsageLine::STATE_OVER,
            $used >= $limit * self::NEAR_RATIO => UsageLine::STATE_NEAR,
            default => UsageLine::STATE_OK,
        };
    }
}
