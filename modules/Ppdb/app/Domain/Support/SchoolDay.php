<?php

namespace Modules\Ppdb\App\Domain\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * The school's own calendar: admissions days are the school's days in the
 * tenant's timezone, never the server's. Kept apart from Attendance's
 * clock because feature modules never import each other.
 */
final class SchoolDay
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * Today's date at the school, `Y-m-d`.
     */
    public function today(): string
    {
        return CarbonImmutable::now($this->context->timezone())->toDateString();
    }

    /**
     * The school's day (`Y-m-d`) a stored moment falls on.
     */
    public function dateOf(CarbonInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->setTimezone($this->context->timezone())->toDateString();
    }

    /**
     * "30 Juni 2027" for a `Y-m-d` date, in Indonesian whatever the
     * application locale.
     */
    public function label(string $date): string
    {
        /** @var CarbonImmutable $localized */
        $localized = CarbonImmutable::parse($date)->locale('id');

        return $localized->isoFormat('D MMMM Y');
    }
}
