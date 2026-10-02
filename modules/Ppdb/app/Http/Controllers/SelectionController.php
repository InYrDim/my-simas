<?php

namespace Modules\Ppdb\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ppdb\App\Domain\Actions\PublishResults;
use Modules\Ppdb\App\Domain\Actions\SaveSelection;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Queries\ChosenPeriod;
use Modules\Ppdb\App\Domain\Queries\PathRanking;
use Modules\Ppdb\App\Domain\Support\SchoolDay;
use Modules\Ppdb\App\Http\Requests\SelectionRequest;

/**
 * Seleksi & Pengumuman: the committee ranks the verified applicants of
 * each path by score, decides (accepted, waiting list, not accepted)
 * within the path's quota, and announces the results of the period.
 */
final class SelectionController
{
    public function index(Request $request, ChosenPeriod $chosen, PathRanking $ranking, SchoolDay $day): Response
    {
        $period = $chosen->forRequest($request->integer('periode'));

        if ($period === null) {
            return Inertia::render('Ppdb/Selection', [
                'period' => null,
                'paths' => [],
                'selectedPathId' => null,
                'quota' => ['capacity' => 0, 'accepted' => 0, 'waitlist' => 0],
                'candidates' => [],
                'pending' => 0,
                'can' => ['manage' => Gate::allows('ppdb.selection.manage')],
            ]);
        }

        $counts = $ranking->counts($period);
        $selected = $period->paths->firstWhere('id', $request->integer('jalur')) ?? $period->paths->first();
        $zero = ['verified' => 0, 'accepted' => 0, 'waitlist' => 0, 'pending' => 0];

        return Inertia::render('Ppdb/Selection', [
            'period' => [
                'id' => $period->id,
                'name' => $period->name,
                'status' => $period->status->value,
                'resultsPublished' => $period->results_published_at !== null,
                'publishedOn' => $period->results_published_at === null ? null : $day->label($day->dateOf($period->results_published_at)),
            ],
            'paths' => $period->paths->map(fn (AdmissionPath $path): array => [
                'id' => $path->id,
                'name' => $path->name,
                'quota' => $path->quota,
                ...($counts[$path->id] ?? $zero),
            ])->values()->all(),
            'selectedPathId' => $selected?->id,
            'quota' => [
                'capacity' => $selected->quota ?? 0,
                'accepted' => ($counts[$selected?->id] ?? $zero)['accepted'],
                'waitlist' => ($counts[$selected?->id] ?? $zero)['waitlist'],
            ],
            'candidates' => $selected === null ? [] : array_map(fn (array $row): array => [
                'id' => $row['applicant']->id,
                'rank' => $row['rank'],
                'number' => $row['applicant']->number,
                'name' => $row['applicant']->name,
                'score' => $row['applicant']->score === null ? null : number_format((float) $row['applicant']->score, 2, '.', ''),
                'decision' => $row['applicant']->decision->value,
                'enrolled' => $row['applicant']->isEnrolled(),
            ], $ranking->forPath($selected)),
            'pending' => array_sum(array_column($counts, 'pending')),
            'can' => ['manage' => Gate::allows('ppdb.selection.manage')],
        ]);
    }

    public function update(SelectionRequest $request, SaveSelection $save): RedirectResponse
    {
        $path = AdmissionPath::query()->findOrFail($request->integer('path_id'));
        $period = AdmissionPeriod::query()->findOrFail($path->period_id);

        /** @var list<array{applicant_id: int, score: string|null, decision: string}> $rows */
        $rows = array_values($request->validated('rows'));

        $save->handle($period, $path, $rows);

        return back()->with('status', "Seleksi jalur {$path->name} disimpan.");
    }

    public function publish(Request $request, PublishResults $publish): RedirectResponse
    {
        $request->validate(['period_id' => ['required', 'integer']]);

        $publish->handle(AdmissionPeriod::query()->findOrFail($request->integer('period_id')));

        return back()->with('status', 'Hasil seleksi diumumkan. Pendaftar bisa melihatnya di halaman akun mereka.');
    }
}
