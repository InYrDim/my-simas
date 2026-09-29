<?php

namespace Modules\Platform\App\Infrastructure\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Modules\Platform\App\Domain\Models\ProviderUser;

class ProviderCreateUserCommand extends Command
{
    protected $signature = 'provider:create-user
        {name : Full name}
        {email : Login email (unique)}
        {--password= : Password (omit for a hidden interactive prompt)}';

    protected $description = 'Create a SaaS provider staff account (central, provider guard)';

    public function handle(): int
    {
        // --password for non-interactive use (provisioning scripts,
        // tests); the hidden prompt keeps passwords out of shell history
        // when run by hand.
        $password = (string) ($this->option('password') ?: $this->secret('Password (min 8 characters)'));

        $validation = Validator::make(
            ['name' => $this->argument('name'), 'email' => $this->argument('email'), 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:provider_users,email'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = ProviderUser::query()->create([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => Hash::make($password),
        ]);

        $this->info("Provider user [{$user->email}] created (guard: provider).");

        return self::SUCCESS;
    }
}
