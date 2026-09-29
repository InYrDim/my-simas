<?php

namespace Modules\Platform\App\Infrastructure\Commands;

use Illuminate\Console\Command;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class TenantRunCommand extends Command
{
    protected $signature = 'tenant:run
        {tenant : Tenant slug}
        {artisan-command* : The artisan command to run, e.g. "migrate" or "db:seed --class=Foo"}';

    protected $description = 'Run an artisan command inside a tenant context';

    public function handle(TenantContext $context): int
    {
        $slug = mb_strtolower(trim((string) $this->argument('tenant')));

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            $this->error("Tenant [{$slug}] not found.");

            return self::FAILURE;
        }

        $tokens = (array) $this->argument('artisan-command');

        if ($tokens === []) {
            $this->error('No artisan command given. Example: tenant:run sekolah-a migrate');

            return self::FAILURE;
        }

        $name = array_shift($tokens);

        if (! $this->getApplication()->has($name)) {
            $this->error("Unknown artisan command [{$name}].");

            return self::FAILURE;
        }

        $command = $this->getApplication()->find($name);

        // StringInput parses the raw tokens against the target
        // command's own definition — no hand-rolled option/argument
        // splitting (which breaks on commands without list arguments).
        $input = new StringInput($name.' '.implode(' ', $tokens));

        // Cron-based workers on shared hosting run through this: the
        // command executes with the tenant context fully set (queue
        // payloads stamped, tenant-scoped models filtered). The inner
        // command's output is captured and relayed so operators still
        // see it.
        $buffer = new BufferedOutput;

        $exit = $context->run($tenant->id, function () use ($command, $input, $buffer): int {
            return $command->run($input, $buffer);
        });

        $inner = rtrim((string) $buffer->fetch());

        if ($inner !== '') {
            $this->line($inner);
        }

        $this->info("[{$slug}] {$name} finished with exit code {$exit}.");

        return $exit;
    }
}
