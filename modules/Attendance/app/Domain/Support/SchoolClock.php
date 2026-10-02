<?php

namespace Modules\Attendance\App\Domain\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * The school's own clock: attendance is dated and timed in the tenant's
 * timezone, never the server's.
 */
final class SchoolClock
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->context->timezone());
    }

    /**
     * Today's date at the school, `Y-m-d`.
     */
    public function today(): string
    {
        return $this->now()->toDateString();
    }

    /**
     * A stored moment on the school's clock.
     */
    public function local(CarbonInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)->setTimezone($this->context->timezone());
    }

    /**
     * A moment as it goes into the database: Eloquent writes a date in the
     * timezone the object carries, so a moment on the school's clock is
     * moved to the application's first.
     */
    public function stored(CarbonInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)->setTimezone((string) config('app.timezone'));
    }

    /**
     * A stored moment as the school's time of day, `H:i`; null stays null.
     */
    public function time(?CarbonInterface $moment): ?string
    {
        return $moment === null ? null : $this->local($moment)->format('H:i');
    }

    /**
     * "Jumat, 2 Oktober 2026" for a `Y-m-d` date.
     */
    public function dateLabel(string $date): string
    {
        return $this->format(CarbonImmutable::parse($date), 'dddd, D MMMM Y');
    }

    /**
     * "Oktober 2026" for a `Y-m` month.
     */
    public function monthLabel(string $month): string
    {
        return $this->format(CarbonImmutable::parse("{$month}-01"), 'MMMM Y');
    }

    /**
     * "Okt" for the month a moment falls in.
     */
    public function shortMonth(CarbonInterface $moment): string
    {
        return $this->format(CarbonImmutable::instance($moment), 'MMM');
    }

    /**
     * Dates are written in Indonesian, whatever the application locale.
     */
    private function format(CarbonImmutable $moment, string $pattern): string
    {
        /** @var CarbonImmutable $localized */
        $localized = $moment->locale('id');

        return $localized->isoFormat($pattern);
    }

    /**
     * The weekday of a `Y-m-d` date: 1 (Senin) to 7 (Minggu).
     */
    public function weekday(string $date): int
    {
        return CarbonImmutable::parse($date)->dayOfWeekIso;
    }
}
