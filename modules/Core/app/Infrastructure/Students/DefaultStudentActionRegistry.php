<?php

namespace Modules\Core\App\Infrastructure\Students;

use Illuminate\Contracts\Container\Container;
use Modules\Core\App\Contracts\DTOs\StudentAction;
use Modules\Core\App\Contracts\StudentActionProvider;
use Modules\Core\App\Contracts\StudentActionRegistry;
use Modules\Platform\App\Contracts\TenantModules;
use Throwable;

/**
 * In-memory registry filled by each module's service provider at boot. The
 * read side asks the providers of the modules active for the current
 * tenant; one that throws is reported and skipped.
 */
final class DefaultStudentActionRegistry implements StudentActionRegistry
{
    /**
     * @var array<class-string<StudentActionProvider>, string> provider class => module key
     */
    private array $modules = [];

    public function __construct(
        private readonly Container $container,
        private readonly TenantModules $tenantModules,
    ) {}

    public function register(string $module, string $provider): void
    {
        $this->modules[$provider] = $module;
    }

    /**
     * @return list<array{key: string, label: string, allUrl: string, studentUrl: string}>
     */
    public function actionsForCurrentUser(): array
    {
        $actions = [];

        foreach ($this->modules as $class => $module) {
            if (! $this->tenantModules->isEnabled($module)) {
                continue;
            }

            try {
                foreach ($this->container->make($class)->actions() as $action) {
                    $actions[] = $this->toArray($action);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $actions;
    }

    /**
     * @return array{key: string, label: string, allUrl: string, studentUrl: string}
     */
    private function toArray(StudentAction $action): array
    {
        return [
            'key' => $action->key,
            'label' => $action->label,
            'allUrl' => $action->allUrl,
            'studentUrl' => $action->studentUrl,
        ];
    }
}
