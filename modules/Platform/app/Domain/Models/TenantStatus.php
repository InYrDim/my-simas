<?php

namespace Modules\Platform\App\Domain\Models;

/**
 * Tenant lifecycle status. Enum-ish so the model keeps a canonical value
 * object; persisted as its string value.
 */
enum TenantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
