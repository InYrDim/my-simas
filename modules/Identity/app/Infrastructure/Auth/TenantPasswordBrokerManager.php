<?php

namespace Modules\Identity\App\Infrastructure\Auth;

use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Broker manager whose token repositories are tenant-scoped. Only the
 * repository creation differs from Laravel's — the broker itself (send
 * reset link / reset flow / timebox) is untouched.
 */
final class TenantPasswordBrokerManager extends PasswordBrokerManager
{
    /**
     * Laravel's createTokenRepository() builds a DatabaseTokenRepository
     * keyed by email alone; swap in the tenant-scoped subclass. Config
     * shape is unchanged (table/expire/throttle/connection).
     *
     * @param  array<string, mixed>  $config
     * @return TokenRepositoryInterface
     */
    protected function createTokenRepository(array $config)
    {
        $key = $this->app->make('config')->get('app.key');

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return new TenantDatabaseTokenRepository(
            $this->app->make('db')->connection($config['connection'] ?? null),
            $this->app->make('hash'),
            $config['table'],
            $key,
            ($config['expire'] ?? 60) * 60,
            $config['throttle'] ?? 0,
            $this->app->make(TenantContext::class),
        );
    }
}
