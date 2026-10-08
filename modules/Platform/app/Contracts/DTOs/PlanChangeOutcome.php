<?php

namespace Modules\Platform\App\Contracts\DTOs;

/**
 * What a school's plan change turned into: `immediate` (during a trial),
 * `upgrade` (an invoice for the prorated difference is waiting for
 * payment; the current plan stays until it is paid) or `scheduled` (a
 * downgrade that takes effect when the paid period ends).
 */
final readonly class PlanChangeOutcome
{
    public const IMMEDIATE = 'immediate';

    public const UPGRADE = 'upgrade';

    public const SCHEDULED = 'scheduled';

    public function __construct(
        public string $kind,
        public ?InvoiceSummary $invoice = null,
        public ?string $scheduledPlanName = null,
        public ?string $takesEffectOn = null,
    ) {}
}
