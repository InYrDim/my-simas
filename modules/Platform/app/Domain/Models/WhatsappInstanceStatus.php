<?php

namespace Modules\Platform\App\Domain\Models;

/**
 * Where a school's WhatsApp instance stands with the provider. Whether the
 * number is linked is a separate matter (`connection_status`).
 */
enum WhatsappInstanceStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Disabled = 'disabled';
}
