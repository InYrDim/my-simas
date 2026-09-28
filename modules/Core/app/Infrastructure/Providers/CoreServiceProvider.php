<?php

namespace Modules\Core\App\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../../routes/web.php');
    }
}
