<?php

use App\Providers\AppServiceProvider;
use Modules\Attendance\App\Infrastructure\Providers\AttendanceServiceProvider;
use Modules\Core\App\Infrastructure\Providers\CoreServiceProvider;
use Modules\Identity\App\Infrastructure\Auth\TenantPasswordResetServiceProvider;
use Modules\Identity\App\Infrastructure\Providers\IdentityServiceProvider;
use Modules\Platform\App\Infrastructure\Providers\PlatformServiceProvider;
use Modules\Ppdb\App\Infrastructure\Providers\PpdbServiceProvider;
use Modules\Shared\App\Infrastructure\Providers\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    PlatformServiceProvider::class,
    IdentityServiceProvider::class,
    CoreServiceProvider::class,
    AttendanceServiceProvider::class,
    PpdbServiceProvider::class,

    // Tenant-scoped password broker (Fase 2 Stage 4). MUST be a
    // top-level provider: it replaces bindings of Laravel's DEFERRED
    // PasswordResetServiceProvider, and registering it nested inside
    // another provider would let the deferred one win at first
    // resolution (deferred map => provider class). By registering it
    // here, the deferred map points at OUR provider (it inherits the
    // parent's isDeferred()/provides()) and Laravel's never loads.
    TenantPasswordResetServiceProvider::class,
];
