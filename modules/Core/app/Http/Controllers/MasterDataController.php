<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\App\Infrastructure\Mock\MasterMockData;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Master-data mockup pages, in three groups: base data that must exist
 * (school profile, academic years, classes, subjects, people, rooms,
 * extracurriculars), academic management that works on it (student
 * placement, teaching assignments, homerooms, bell schedule, academic
 * calendar), and data import.
 *
 * Mock phase: every page reads static sample data from MasterMockData and
 * nothing is persisted. The DB phase splits this into one thin controller
 * per entity backed by real repositories; the page props stay the same.
 */
final class MasterDataController
{
    private const LEVEL_SESSION_KEY = 'master_mock_level';

    public function __construct(private readonly TenantContext $context) {}

    public function school(Request $request): Response
    {
        return $this->render($request, 'Core/Master/School/Show');
    }

    public function academicYears(Request $request): Response
    {
        return $this->render($request, 'Core/Master/AcademicYears/Index', fn (MasterMockData $data): array => $data->academicYears());
    }

    public function grades(Request $request): Response
    {
        return $this->render($request, 'Core/Master/Grades/Index', fn (MasterMockData $data): array => [
            'grades' => $data->grades(),
            'majors' => $data->majors(),
        ]);
    }

    public function classes(Request $request): Response
    {
        return $this->render($request, 'Core/Master/Classes/Index', fn (MasterMockData $data): array => [
            'classes' => $data->classes(),
            'grades' => $data->grades(),
            'majors' => $data->majors(),
            'years' => $data->academicYears()['years'],
        ]);
    }

    public function classShow(Request $request, int $id): Response
    {
        return $this->render($request, 'Core/Master/Classes/Show', function (MasterMockData $data) use ($id): array {
            $class = $this->find($data->classes(), $id);

            return [
                'class' => $class,
                'students' => array_values(array_filter(
                    $data->students(),
                    fn (array $student): bool => $student['classId'] === $class['id'],
                )) ?: array_slice($data->students(), 0, 8),
                'assignments' => array_values(array_filter(
                    $data->assignments(),
                    fn (array $row): bool => $row['class'] === $class['name'],
                )) ?: array_slice($data->assignments(), 0, 5),
            ];
        });
    }

    public function subjects(Request $request): Response
    {
        return $this->render($request, 'Core/Master/Subjects/Index', fn (MasterMockData $data): array => [
            'subjects' => $data->subjects(),
        ]);
    }

    public function teachers(Request $request): Response
    {
        return $this->render($request, 'Core/Master/Teachers/Index', fn (MasterMockData $data): array => [
            'teachers' => $data->teachers(),
        ]);
    }

    public function teacherShow(Request $request, int $id): Response
    {
        return $this->render($request, 'Core/Master/Teachers/Show', function (MasterMockData $data) use ($id): array {
            $teacher = $this->find($data->teachers(), $id);

            return [
                'teacher' => $teacher,
                'assignments' => array_values(array_filter(
                    $data->assignments(),
                    fn (array $row): bool => $row['teacher'] === $teacher['name'],
                )),
                'homeroomOf' => array_values(array_filter(
                    $data->classes(),
                    fn (array $class): bool => $class['homeroomId'] === $teacher['id'],
                )),
            ];
        });
    }

    public function students(Request $request): Response
    {
        return $this->render($request, 'Core/Master/Students/Index', fn (MasterMockData $data): array => [
            'students' => $data->students(),
            'classes' => $data->classes(),
        ]);
    }

    public function studentShow(Request $request, int $id): Response
    {
        return $this->render($request, 'Core/Master/Students/Show', fn (MasterMockData $data): array => [
            'student' => $this->find($data->students(), $id),
            'history' => [
                ['year' => '2025/2026', 'class' => $data->classes()[0]['name'], 'note' => 'Kelas aktif'],
                ['year' => '2024/2025', 'class' => $data->classes()[0]['name'], 'note' => 'Naik kelas'],
            ],
        ]);
    }

    public function placement(Request $request): Response
    {
        return $this->render($request, 'Core/Academic/Placement/Index', fn (MasterMockData $data): array => [
            'years' => $data->academicYears()['years'],
            'classes' => $data->classes(),
            'students' => array_values(array_filter(
                $data->students(),
                fn (array $student): bool => $student['status'] === 'active',
            )),
        ]);
    }

    public function assignments(Request $request): Response
    {
        return $this->render($request, 'Core/Academic/Assignments/Index', fn (MasterMockData $data): array => [
            'assignments' => $data->assignments(),
            'classes' => $data->classes(),
            'teachers' => $data->teachers(),
        ]);
    }

    public function homerooms(Request $request): Response
    {
        return $this->render($request, 'Core/Academic/Homerooms/Index', fn (MasterMockData $data): array => [
            'classes' => $data->classes(),
            'teachers' => $data->teachers(),
        ]);
    }

    public function importData(Request $request): Response
    {
        return $this->render($request, 'Core/Manage/Import/Index', fn (MasterMockData $data): array => [
            'preview' => array_slice($data->students(), 0, 6),
        ]);
    }

    public function rooms(Request $request): Response
    {
        return $this->render($request, 'Core/Master/Rooms/Index', fn (MasterMockData $data): array => [
            'rooms' => $data->rooms(),
        ]);
    }

    public function periods(Request $request): Response
    {
        return $this->render($request, 'Core/Academic/Periods/Index', fn (MasterMockData $data): array => [
            'days' => $data->periods(),
        ]);
    }

    public function calendar(Request $request): Response
    {
        return $this->render($request, 'Core/Academic/Calendar/Index', fn (MasterMockData $data): array => [
            'events' => $data->calendarEvents(),
        ]);
    }

    public function extracurriculars(Request $request): Response
    {
        return $this->render($request, 'Core/Master/Extracurriculars/Index', fn (MasterMockData $data): array => [
            'extracurriculars' => array_map(
                fn (array $row): array => array_diff_key($row, ['memberList' => true]),
                $data->extracurriculars(),
            ),
            'teachers' => $data->teachers(),
        ]);
    }

    public function extracurricularShow(Request $request, int $id): Response
    {
        return $this->render($request, 'Core/Master/Extracurriculars/Show', fn (MasterMockData $data): array => [
            'extracurricular' => $this->find($data->extracurriculars(), $id),
        ]);
    }

    /**
     * Render a page with the school summary every master page needs
     * (level, labels, level switcher for the mock).
     *
     * @param  (callable(MasterMockData): array<string, mixed>)|null  $props
     */
    private function render(Request $request, string $component, ?callable $props = null): Response
    {
        $level = MasterMockData::normaliseLevel(
            $request->query('jenjang', $request->session()->get(self::LEVEL_SESSION_KEY)),
        );
        $request->session()->put(self::LEVEL_SESSION_KEY, $level);

        $data = new MasterMockData($level);
        $tenant = $this->context->currentOrFail();

        return Inertia::render($component, [
            'school' => $data->school($tenant->name, $tenant->slug, $tenant->timezone),
            ...($props === null ? [] : $props($data)),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function find(array $rows, int $id): array
    {
        foreach ($rows as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }

        abort(404);
    }
}
