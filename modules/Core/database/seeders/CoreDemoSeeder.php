<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\App\Domain\Actions\ActivateAcademicYear;
use Modules\Core\App\Domain\Actions\CreateAcademicYear;
use Modules\Core\App\Domain\Actions\SaveClassGroup;
use Modules\Core\App\Domain\Actions\SaveExtracurricular;
use Modules\Core\App\Domain\Actions\SaveMajor;
use Modules\Core\App\Domain\Actions\SaveRoom;
use Modules\Core\App\Domain\Actions\SaveSchoolProfile;
use Modules\Core\App\Domain\Actions\SaveStudent;
use Modules\Core\App\Domain\Actions\SaveSubject;
use Modules\Core\App\Domain\Actions\SaveTeacher;
use Modules\Core\App\Domain\Actions\SyncDefaultGrades;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\Major;
use Modules\Core\App\Domain\Models\Room;
use Modules\Core\App\Domain\Models\SchoolProfile;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Infrastructure\Mock\MasterMockData;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantDirectory;

/**
 * Fills a school with sample master data (an SMA with three academic years,
 * classes, people and activities) so the pages have something to show.
 * Development only; skips schools that already have an academic year.
 *
 *   php artisan db:seed --class="Modules\Core\Database\Seeders\CoreDemoSeeder"
 */
class CoreDemoSeeder extends Seeder
{
    public function run(TenantContext $context, TenantDirectory $tenants): void
    {
        foreach ($tenants->all() as $tenant) {
            $context->run($tenant->id, fn () => $this->seedSchool());
        }
    }

    private function seedSchool(): void
    {
        if (AcademicYear::query()->exists()) {
            return;
        }

        $mock = new MasterMockData('sma');

        app(SaveSchoolProfile::class)->handle([
            'level' => 'sma', 'npsn' => '20512345', 'ownership' => 'Swasta', 'accreditation' => 'A',
            'address' => 'Jl. Pendidikan No. 12, Kota Bandung', 'phone' => '(022) 555-0123',
            'email' => 'info@sekolah-contoh.sch.id', 'headmaster' => 'Dra. Siti Rahmawati, M.Pd.',
        ]);
        app(SyncDefaultGrades::class)->handle(SchoolProfile::current());

        foreach ([['2024/2025', 2024], ['2025/2026', 2025], ['2026/2027', 2026]] as [$name, $startYear]) {
            $year = app(CreateAcademicYear::class)->handle([
                'name' => $name, 'curriculum' => 'Kurikulum Merdeka',
                'start_date' => "{$startYear}-07-14", 'end_date' => ($startYear + 1).'-06-27',
            ]);

            if ($startYear === 2025) {
                app(ActivateAcademicYear::class)->handle($year);
            }
        }

        foreach ($mock->majors() as $major) {
            app(SaveMajor::class)->handle(null, ['code' => $major['code'], 'name' => $major['name'], 'kind' => $major['kind'], 'concentrations' => $major['concentrations']]);
        }

        foreach ($mock->rooms() as $room) {
            app(SaveRoom::class)->handle(null, ['code' => $room['code'], 'name' => $room['name'], 'type' => $room['type'], 'capacity' => $room['capacity'], 'status' => $room['status']]);
        }

        foreach ($mock->subjects() as $subject) {
            app(SaveSubject::class)->handle(null, ['code' => $subject['code'], 'name' => $subject['name'], 'group' => $subject['group'], 'kkm' => $subject['kkm']]);
        }

        foreach ($mock->teachers() as $teacher) {
            app(SaveTeacher::class)->handle(null, [
                'name' => $teacher['name'], 'nip' => $teacher['nip'] === '—' ? null : $teacher['nip'], 'nuptk' => $teacher['nuptk'],
                'employment' => $teacher['employment'], 'duty' => $teacher['duty'], 'email' => $teacher['email'],
            ]);
        }

        $this->seedClasses();
        $this->seedStudents($mock);
        $this->seedActivities($mock);
    }

    private function seedClasses(): void
    {
        $year = AcademicYear::query()->where('status', 'active')->firstOrFail();
        $rooms = Room::query()->where('type', 'Kelas')->get();

        foreach (Grade::query()->orderBy('sort_order')->get() as $grade) {
            foreach (Major::query()->orderBy('code')->get() as $major) {
                foreach ([1, 2] as $number) {
                    app(SaveClassGroup::class)->handle(null, [
                        'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'major_id' => $major->id,
                        'room_id' => $rooms->random()->id, 'name' => "{$grade->name} {$major->code} {$number}",
                    ]);
                }
            }
        }
    }

    private function seedStudents(MasterMockData $mock): void
    {
        $classIds = ClassGroup::query()->pluck('id')->all();

        foreach ($mock->students() as $index => $student) {
            app(SaveStudent::class)->handle(null, [
                'name' => $student['name'], 'nis' => $student['nis'], 'nisn' => $student['nisn'], 'gender' => $student['gender'],
                'birth_date' => $student['birth'], 'guardian_name' => $student['guardian'], 'guardian_phone' => $student['guardianPhone'],
                'status' => $student['status'], 'class_id' => $classIds[$index % count($classIds)],
            ]);
        }
    }

    private function seedActivities(MasterMockData $mock): void
    {
        $coaches = Teacher::query()->pluck('id')->all();

        foreach ($mock->extracurriculars() as $index => $activity) {
            app(SaveExtracurricular::class)->handle(null, [
                'name' => $activity['name'], 'coach_teacher_id' => $coaches[($index + 1) % count($coaches)],
                'schedule' => $activity['schedule'], 'kind' => $activity['kind'],
            ]);
        }
    }
}
