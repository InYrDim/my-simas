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
|   Vendor / Laravel -> Shared -> Platform -> Identity -> Core -> App
|
| Each module has an internal layer plus a Public layer (App/Contracts
| only, per the module template). Modules may depend on other modules
| ONLY through their Public layer. A module's Public layer may depend on
| its own internal layer (public traits may delegate to internal
| machinery); it must never pull another module's internals. Root
| tests/ is its own glue layer (may use anything, nothing depends on
| it). See docs/architecture/modular-monolith.md for the full policy.
|
| Notes:
|  - ClassLikeConfig::create() doubles every backslash in the given
|    string when building the final PCRE. Patterns below therefore use
|    single backslashes exactly like a normal PHP string: '^App\.*'
|    becomes the PCRE /^App\\.*$/i and matches App\Foo\Bar. Writing
|    '\\\\' here produces a pattern that matches nothing (verified in
|    Fase 1 — the doubled variants silently disabled every layer).
|  - The App layer is anchored (^App\) so it never swallows the
|    Modules\<Name>\App\... namespaces of module-internal code.
*/

return static function (DeptracConfig $config): void {
    $config
        ->paths('./app', './modules', './database')
        ->excludeFiles('#[\\/]tests[\\/]#')
        ->layers(
            $app = Layer::withName('App')->collectors(
                ClassLikeConfig::create('^App\.*'),
            ),
            $vendor = Layer::withName('Vendor')->collectors(
                // Anything that is not App\, Modules\ or Database\.
                ClassLikeConfig::create('^(?!(App|Modules|Database)\).*$'),
            ),
            $laravel = Layer::withName('Laravel')->collectors(
                ClassLikeConfig::create('.*Illuminate\.*'),
                ClassLikeConfig::create('.*Laravel\.*'),
            ),
            $database = Layer::withName('Database')->collectors(
                // Root seeders/factories glue (e.g. DatabaseSeeder creates
                // the example Identity user). Known, documented exception:
                // seeding is app-level wiring, not a module consuming
                // another module. Anchored so it never swallows
                // Modules\<Name>\Database\* or Illuminate\Database\*.
                ClassLikeConfig::create('^Database\.*'),
            ),
            $shared = Layer::withName('Shared')->collectors(
                // Shared has no Contracts restriction; all of it is public.
                ClassLikeConfig::create('.*Modules\Shared\.*'),
            ),
            $platform = Layer::withName('Platform')->collectors(
                // Platform internals: tenancy, module registry, permission
                // registry, Spatie integration.
                ClassLikeConfig::create('.*Modules\Platform\App\(?!Contracts\).*'),
                ClassLikeConfig::create('.*Modules\Platform\(Database|Tests|routes|resources)\.*'),
            ),
            $platformPublic = Layer::withName('PlatformPublic')->collectors(
                ClassLikeConfig::create('.*Modules\Platform\App\Contracts\.*'),
            ),
            $identity = Layer::withName('Identity')->collectors(
                ClassLikeConfig::create('.*Modules\Identity\App\(?!Contracts\).*'),
                ClassLikeConfig::create('.*Modules\Identity\(Database|Tests|routes|resources)\.*'),
            ),
            $identityPublic = Layer::withName('IdentityPublic')->collectors(
                ClassLikeConfig::create('.*Modules\Identity\App\Contracts\.*'),
            ),
            $core = Layer::withName('Core')->collectors(
                ClassLikeConfig::create('.*Modules\Core\App\(?!Contracts\).*'),
                ClassLikeConfig::create('.*Modules\Core\(Database|Tests|routes|resources)\.*'),
            ),
            $tests = Layer::withName('Tests')->collectors(
                // Root test suite glue (arch tests etc.). Nothing may depend
                // on it; it may reach anything.
                ClassLikeConfig::create('^Tests\.*'),
            ),
            $corePublic = Layer::withName('CorePublic')->collectors(
                ClassLikeConfig::create('.*Modules\Core\App\Contracts\.*'),
            ),
        )
        ->rulesets(
            // App is Laravel glue only: wiring, never business logic.
            Ruleset::forLayer($app)->accesses(
                $shared, $laravel, $vendor,
                $identity, $identityPublic,
                $core, $corePublic, $platformPublic,
            ),
            // Shared: pure technical utilities, no module knowledge.
            Ruleset::forLayer($shared)->accesses($laravel, $vendor),
            // Root tests are free glue.
            Ruleset::forLayer($tests)->accesses(
                $app, $database, $shared, $laravel, $vendor,
                $platform, $platformPublic,
                $identity, $identityPublic,
                $core, $corePublic,
            ),
            // Platform: tenancy/permissions, depends on Shared only.
            Ruleset::forLayer($platform)->accesses($platformPublic, $shared, $laravel, $vendor),
            // Public may use its OWN internals (e.g. a public trait
            // delegating to internal machinery) — never another module's.
            Ruleset::forLayer($platformPublic)->accesses($platform, $shared),
            // Identity: auth + user identity.
            Ruleset::forLayer($identity)->accesses(
                $identityPublic, $shared, $platformPublic, $laravel, $vendor,
            ),
            Ruleset::forLayer($identityPublic)->accesses($identity, $shared),
            // Core: master data. May use Identity's public surface, never
            // feature modules.
            Ruleset::forLayer($core)->accesses(
                $corePublic, $shared, $platformPublic, $identityPublic, $laravel, $vendor,
            ),
            Ruleset::forLayer($corePublic)->accesses($core, $shared),
            // Database glue: see note on the layer above.
            Ruleset::forLayer($database)->accesses(
                $shared, $laravel, $vendor,
                $identity, $identityPublic,
                $core, $corePublic, $platformPublic,
            ),
        );
};
