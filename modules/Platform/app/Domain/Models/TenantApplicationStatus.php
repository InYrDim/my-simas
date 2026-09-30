<?php

namespace Modules\Platform\App\Domain\Models;

/**
 * Tenant application lifecycle. Enum-ish so the model keeps a canonical
 * value object; persisted as its string value.
 */
enum TenantApplicationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
