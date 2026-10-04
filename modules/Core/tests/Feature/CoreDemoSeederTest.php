<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\TimetableEntry;
use Modules\Core\Database\Seeders\CoreDemoSeeder;
use Modules\Platform\Database\Factories\TenantFactory;

require_once __DIR__.'/Support/helpers.php';

it('fills a school with consistent sample master data, once', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'demo-seed']);

    $this->seed(CoreDemoSeeder::class);
    $this->seed(CoreDemoSeeder::class);

    inSchool($tenant, function (): void {
        expect(AcademicYear::query()->count())->toBe(3)
            ->and(AcademicYear::query()->where('status', 'active')->count())->toBe(1)
            ->and(ClassGroup::query()->count())->toBe(18)
            ->and(Student::query()->count())->toBe(24)
            ->and(Student::query()->whereNotNull('class_id')->count())->toBeGreaterThan(0)
            ->and(TimetableEntry::query()->count())->toBeGreaterThan(0);
    });
});
