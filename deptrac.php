<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

/*
|--------------------------------------------------------------------------
| Modular Monolith — dependency rules (Deptrac 4.x, PHP-based config)
|--------------------------------------------------------------------------
|
| Layer order (bottom -> top):
|   Vendor / Laravel -> Shared -> Platform (Fase 1) -> Identity -> Core -> App
|
| Each module has an internal layer plus a Public layer (Contracts only).
| Modules may depend on other modules ONLY through their Public layer.
| See docs/architecture/modular-monolith.md for the full policy.
|
| Note: ClassLikeConfig::create() doubles backslashes when building the
| final PCRE, so write patterns here exactly like a normal PHP string
| containing single backslashes.
*/

return static function (DeptracConfig $config): void {
    $config
        ->paths('./app', './modules', './database')
        ->layers(
            $app = Layer::withName('App')->collectors(
                ClassLikeConfig::create('.*App\\.*'),
            ),
            $database = Layer::withName('Database')->collectors(
                // Root seeders/factories glue (e.g. DatabaseSeeder creates the
                // example Identity user). Known, documented exception: seeding
                // is app-level wiring, not a module consuming another module.
                // Anchored: must not swallow Modules\*\Database\* or vendor
                // Illuminate\Database\* classes.
                ClassLikeConfig::create('^Database\\.*'),
            ),
            $vendor = Layer::withName('Vendor')->collectors(
                // Anything that is not App\, Modules\, Tests\ or Database\.
                ClassLikeConfig::create('^(?!.*(App|Modules|Tests|Database)\\).*$'),
            ),
            $laravel = Layer::withName('Laravel')->collectors(
                ClassLikeConfig::create('.*Illuminate\\.*'),
                ClassLikeConfig::create('.*Laravel\\.*'),
            ),
            $shared = Layer::withName('Shared')->collectors(
                ClassLikeConfig::create('.*Modules\\Shared\\.*'),
            ),
            $platform = Layer::withName('Platform')->collectors(
                // Reserved for Fase 1 (tenancy, module registry, permissions).
                ClassLikeConfig::create('.*Modules\\Platform\\(?!Contracts\\).*'),
            ),
            $platformPublic = Layer::withName('PlatformPublic')->collectors(
                ClassLikeConfig::create('.*Modules\\Platform\\Contracts\\.*'),
            ),
            $identity = Layer::withName('Identity')->collectors(
                ClassLikeConfig::create('.*Modules\\Identity\\(?!Contracts\\).*'),
            ),
            $identityPublic = Layer::withName('IdentityPublic')->collectors(
                ClassLikeConfig::create('.*Modules\\Identity\\Contracts\\.*'),
            ),
            $core = Layer::withName('Core')->collectors(
                ClassLikeConfig::create('.*Modules\\Core\\(?!Contracts\\).*'),
            ),
            $corePublic = Layer::withName('CorePublic')->collectors(
                ClassLikeConfig::create('.*Modules\\Core\\Contracts\\.*'),
            ),
        )
        ->rulesets(
            // App is Laravel glue only: wiring, never business logic. It may
            // bind Identity (auth model config) but must not touch other
            // modules' internals.
            Ruleset::forLayer($app)->accesses(
                $shared, $laravel, $vendor,
                $identity, $identityPublic,
                $core, $corePublic, $platformPublic,
            ),
            // Shared: pure technical utilities, no module knowledge.
            Ruleset::forLayer($shared)->accesses($laravel, $vendor),
            // Platform (Fase 1): tenancy/permissions, depends on Shared only.
            Ruleset::forLayer($platform)->accesses($platformPublic, $shared, $laravel, $vendor),
            Ruleset::forLayer($platformPublic)->accesses($shared),
            // Identity: auth + user identity.
            Ruleset::forLayer($identity)->accesses(
                $identityPublic, $shared, $platformPublic, $laravel, $vendor,
            ),
            Ruleset::forLayer($identityPublic)->accesses($shared),
            // Core: master data. May use Identity's public surface, never
            // feature modules.
            Ruleset::forLayer($core)->accesses(
                $corePublic, $shared, $platformPublic, $identityPublic, $laravel, $vendor,
            ),
            Ruleset::forLayer($corePublic)->accesses($shared),
            // Database glue: see note on the layer above.
            Ruleset::forLayer($database)->accesses(
                $shared, $laravel, $vendor,
                $identity, $identityPublic,
                $core, $corePublic, $platformPublic,
            ),
        );
};
