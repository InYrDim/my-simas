<?php

namespace Modules\Ppdb\App\Domain\Dashboard;

use Illuminate\Support\Facades\Gate;
use Modules\Core\App\Contracts\DashboardWidgetProvider;
use Modules\Core\App\Contracts\DTOs\DashboardWidget;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Queries\AwaitingVerification;
use Modules\Ppdb\App\Domain\Queries\ChosenPeriod;

/**
 * Admissions on the Beranda, for the committee: how many applicants the
 * running period has and who is still waiting for their data to be
 * checked. A school with no admissions period has nothing to show.
 */
final class AdmissionDashboard implements DashboardWidgetProvider
{
    public function __construct(
        private readonly ChosenPeriod $chosen,
        private readonly AwaitingVerification $awaiting,
    ) {}

    /**
     * @return list<DashboardWidget>
     */
    public function widgets(): array
    {
        $period = Gate::allows('ppdb.view') ? $this->chosen->forRequest() : null;

        if ($period === null) {
            return [];
        }

        $waiting = $this->awaiting->forPeriod($period);

        return [
            new DashboardWidget(
                key: 'ppdb.applicants-total',
                kind: DashboardWidget::KIND_STAT,
                slot: DashboardWidget::SLOT_FIGURES,
                title: 'Pendaftar PPDB',
                payload: [
                    'value' => Applicant::query()->where('period_id', $period->id)->count(),
                    'hint' => $period->name,
                ],
                order: 30,
                permission: 'ppdb.view',
            ),
            new DashboardWidget(
                key: 'ppdb.awaiting-count',
                kind: DashboardWidget::KIND_STAT,
                slot: DashboardWidget::SLOT_FIGURES,
                title: 'Menunggu verifikasi',
                payload: ['value' => $waiting['count'], 'hint' => 'pendaftar PPDB'],
                order: 31,
                permission: 'ppdb.applicants.manage',
            ),
            new DashboardWidget(
                key: 'ppdb.awaiting-list',
                kind: DashboardWidget::KIND_LIST,
                slot: DashboardWidget::SLOT_ATTENTION,
                title: 'Pendaftar menunggu verifikasi',
                payload: [
                    'items' => array_map(fn (array $applicant): array => [
                        'label' => $applicant['name'],
                        'detail' => "{$applicant['number']} · mendaftar {$applicant['registeredOn']}",
                        'href' => route('ppdb.applicants.show', $applicant['id'], absolute: false),
                    ], $waiting['applicants']),
                    'total' => $waiting['count'],
                    'empty' => 'Tidak ada pendaftar yang menunggu verifikasi.',
                ],
                order: 20,
                href: route('ppdb.applicants', absolute: false),
                permission: 'ppdb.applicants.manage',
            ),
        ];
    }
}
