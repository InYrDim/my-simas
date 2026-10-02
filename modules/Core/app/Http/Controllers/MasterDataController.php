<?php

namespace Modules\Core\App\Http\Controllers;

use Inertia\Response;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Infrastructure\Mock\IntegrationMockData;

/**
 * The page that is still a mockup: the WhatsApp integration. Master data,
 * academic management, the data import and Statistik & Laporan have their
 * own controllers.
 *
 * Mock phase: the page reads static sample data (IntegrationMockData) and
 * nothing is persisted; the page props stay the same when it moves to the
 * database.
 */
final class MasterDataController
{
    use RendersMasterPage;

    public function whatsapp(): Response
    {
        $data = new IntegrationMockData;

        return $this->renderMaster('Core/Integration/Whatsapp/Index', [
            'connection' => $data->whatsappConnection(),
            'notifications' => $data->whatsappNotifications(),
            'template' => $data->whatsappTemplate(),
            'history' => $data->whatsappHistory(),
        ]);
    }
}
