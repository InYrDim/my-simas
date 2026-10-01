<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Response;
use Modules\Core\App\Domain\Models\SchoolProfile;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Infrastructure\Mock\InsightMockData;
use Modules\Core\App\Infrastructure\Mock\IntegrationMockData;
use Modules\Core\App\Infrastructure\Mock\MasterMockData;

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
    use RendersMasterPage;

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

    public function statistics(Request $request): Response
    {
        return $this->render($request, 'Core/Insight/Statistics', fn (MasterMockData $data): array => (new InsightMockData($data))->statistics());
    }

    public function reports(Request $request): Response
    {
        return $this->render($request, 'Core/Insight/Reports', fn (MasterMockData $data): array => (new InsightMockData($data))->reports());
    }

    public function whatsapp(Request $request): Response
    {
        return $this->render($request, 'Core/Integration/Whatsapp/Index', fn (): array => [
            'connection' => ($data = new IntegrationMockData)->whatsappConnection(),
            'notifications' => $data->whatsappNotifications(),
            'template' => $data->whatsappTemplate(),
            'history' => $data->whatsappHistory(),
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

    /**
     * Render a still-mock page: sample data shaped by the school's real
     * level, with the real school summary in the props.
     *
     * @param  (callable(MasterMockData): array<string, mixed>)|null  $props
     */
    private function render(Request $request, string $component, ?callable $props = null): Response
    {
        $data = new MasterMockData(SchoolProfile::current()->level->value);

        return $this->renderMaster($component, $props === null ? [] : $props($data));
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
