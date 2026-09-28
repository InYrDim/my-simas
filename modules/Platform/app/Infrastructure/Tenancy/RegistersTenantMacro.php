<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;

/**
 * Registers the Blueprint::tenantId() macro: a char(26) ULID column with
 * a foreign constraint to tenants.id plus an index. Every tenant-scoped
 * table must create its tenant_id through this macro (sole legal
 * cross-module FK; owned by Platform).
 */
final class RegistersTenantMacro
{
    public static function register(): void
    {
        // Fluent (NOT IndexDefinition): Blueprint::index() returns a
        // plain Fluent command at runtime in this framework version,
        // despite its @return docblock — typing the macro as
        // IndexDefinition throws a TypeError on first real use.
        Blueprint::macro('tenantId', function (): Fluent {
            /** @var Blueprint $this */
            $index = $this->index('tenant_id');

            $this->foreignUlid('tenant_id')->constrained('tenants');

            return $index;
        });
    }
}
