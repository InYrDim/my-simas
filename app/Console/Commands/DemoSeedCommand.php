<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSchoolSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:seed')]
#[Description('Seed the complete demo school (all modules, master data, accounts, attendance, PPDB)')]
class DemoSeedCommand extends Command
{
    public function handle(): int
    {
        if (! app()->isLocal()) {
            $this->components->error('The demo school is seeded in the local environment only.');

            return self::FAILURE;
        }

        return $this->call('db:seed', ['--class' => DemoSchoolSeeder::class]);
    }
}
