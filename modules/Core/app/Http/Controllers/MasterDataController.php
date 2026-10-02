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
 * The pages that are still mockups: data import, statistics, reports and
 * the WhatsApp integration. Master data and academic management have a
 * controller per page backed by the database.
 *
 * Mock phase: each page here reads static sample data (MasterMockData,
 * InsightMockData, IntegrationMockData) and nothing is persisted; the page
 * props stay the same when a page moves to the database.
 */
final class MasterDataController
{
    use RendersMasterPage;

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
}
