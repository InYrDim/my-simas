<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerPagePathCreator();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Resolve the Vite input path for the Inertia page component. Root
     * pages live in resources/js/pages; module pages resolve as
     * "<Module>/<Page>" with the module name DOUBLED (per the resolver
     * in app.tsx, e.g. "Platform/Applications/Show" lives at
     * modules/Platform/resources/js/Pages/Platform/Applications/Show.tsx).
     * The previously hardcoded root path 500s in build (non-hot) mode
     * for every module page: "Unable to locate file in Vite manifest".
     */
    protected function registerPagePathCreator(): void
    {
        View::creator('app', function ($view): void {
            $component = $view->getData()['page']['component'] ?? null;
            $vitePath = null;

            if (is_string($component) && $component !== '') {
                $vitePath = "resources/js/pages/{$component}.tsx";

                if (str_contains($component, '/')) {
                    [$module, $rest] = explode('/', $component, 2);
                    $modulePath = "modules/{$module}/resources/js/Pages/{$module}/{$rest}.tsx";

                    if (is_file(base_path($modulePath))) {
                        $vitePath = $modulePath;
                    }
                }
            }

            $view->with('pageVitePath', $vitePath);
        });
    }
}
