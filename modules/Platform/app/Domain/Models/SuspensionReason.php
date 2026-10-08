<?php

namespace Modules\Platform\App\Domain\Models;

/**
 * Why a school is suspended; persisted as its string value. Only a
 * `billing` suspension is lifted by a payment or a trial extension: a
 * `manual` one (the provider closed the school for another reason) stays
 * until the provider reopens it.
 */
enum SuspensionReason: string
{
    case Billing = 'billing';
    case Manual = 'manual';
}
