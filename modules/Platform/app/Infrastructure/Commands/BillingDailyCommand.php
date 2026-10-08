<?php

namespace Modules\Platform\App\Infrastructure\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Platform\App\Infrastructure\Billing\Daily\BillingDaily;
use Modules\Platform\App\Infrastructure\Billing\Daily\DailyStepResult;
use Throwable;

class BillingDailyCommand extends Command
{
    protected $signature = 'billing:daily
        {--only=* : Run only this step (renewals, reminders, suspensions, payments); repeatable}
        {--date= : Run as if it were this day (Y-m-d), to replay a missed day or look ahead}
        {--dry-run : List what would be done and change nothing}
        {--force : Suspend even past the mass-suspension cap}';

    protected $description = 'Issue renewal invoices, send billing reminders, suspend schools whose access ran out and expire unfinished payments (safe to run twice)';

    public function handle(BillingDaily $daily): int
    {
        $only = array_values(array_filter((array) $this->option('only')));
        $unknown = array_diff($only, BillingDaily::STEPS);

        if ($unknown !== []) {
            $this->error('Unknown step: '.implode(', ', $unknown).'. Choose from: '.implode(', ', BillingDaily::STEPS).'.');

            return self::INVALID;
        }

        $date = null;

        if (filled($this->option('date'))) {
            try {
                $date = Carbon::createFromFormat('!Y-m-d', (string) $this->option('date'));
            } catch (Throwable) {
                $date = null;
            }

            if ($date === null || $date->format('Y-m-d') !== $this->option('date')) {
                $this->error('The --date option must be a valid date in Y-m-d format.');

                return self::INVALID;
            }
        }

        $run = $daily->run($date, $only, (bool) $this->option('dry-run'), (bool) $this->option('force'));

        $this->line(($run->dryRun ? '[dry run] ' : '').'Billing day '.$run->day->toDateString());

        foreach ($run->results as $result) {
            $this->report($result);
        }

        if ($run->isBlocked()) {
            $this->warn('Suspension held back: more schools than the cap allows. Check them, then repeat with --force.');
        }

        return $run->hasFailures() || $run->isBlocked() ? self::FAILURE : self::SUCCESS;
    }

    private function report(DailyStepResult $result): void
    {
        $this->line("<info>{$result->step}</info>: {$result->count}");

        foreach ($result->lines as $line) {
            $this->line("  - {$line}");
        }

        foreach ($result->failures as $failure) {
            $this->error("  ! {$failure}");
        }
    }
}
