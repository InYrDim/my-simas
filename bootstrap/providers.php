<?php

use App\Providers\AppServiceProvider;
use Modules\Core\App\Infrastructure\Providers\CoreServiceProvider;
use Modules\Identity\App\Infrastructure\Providers\IdentityServiceProvider;
use Modules\Platform\App\Infrastructure\Providers\PlatformServiceProvider;
use Modules\Shared\App\Infrastructure\Providers\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    PlatformServiceProvider::class,
    IdentityServiceProvider::class,
    CoreServiceProvider::class,
];
