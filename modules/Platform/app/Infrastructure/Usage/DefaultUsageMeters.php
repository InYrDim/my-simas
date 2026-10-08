<?php

namespace Modules\Platform\App\Infrastructure\Usage;

use Closure;
use Modules\Platform\App\Contracts\UsageMeters;

/**
 * In-memory meter registry populated by each module's service provider.
 */
final class DefaultUsageMeters implements UsageMeters
{
    /**
     * @var array<string, array{module: string, key: string, label: string, unit: string, counter: Closure(): int, limitKey: string}>
     */
    private array $meters = [];

    public function register(string $module, string $key, string $label, string $unit, Closure $counter, ?string $limitKey = null): void
    {
        $this->meters[$key] = [
            'module' => $module,
            'key' => $key,
            'label' => $label,
            'unit' => $unit,
            'counter' => $counter,
            'limitKey' => $limitKey ?? $key,
        ];
    }

    /**
     * @return list<array{module: string, key: string, label: string, unit: string, counter: Closure(): int, limitKey: string}>
     */
    public function all(): array
    {
        return array_values($this->meters);
    }
}
