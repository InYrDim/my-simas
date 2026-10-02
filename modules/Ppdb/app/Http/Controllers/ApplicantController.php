<?php

namespace Modules\Ppdb\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ppdb\App\Domain\Actions\CancelApplication;
use Modules\Ppdb\App\Domain\Actions\RegisterApplicant;
use Modules\Ppdb\App\Domain\Actions\UpdateApplicant;
use Modules\Ppdb\App\Domain\Enums\ApplicantSource;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\FormField;
use Modules\Ppdb\App\Domain\Queries\ChosenPeriod;
use Modules\Ppdb\App\Domain\Queries\FormFieldList;
use Modules\Ppdb\App\Domain\Support\FormRules;
use Modules\Ppdb\App\Domain\Support\SchoolDay;
use Modules\Ppdb\App\Http\Requests\ApplicantRequest;

/**
 * Pendaftar: the applicants of a period for the committee — a searchable
 * list, entering an applicant by hand, and one applicant's page.
 */
final class ApplicantController
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly FormRules $rules,
        private readonly FormFieldList $form,
    ) {}

    public function index(Request $request, ChosenPeriod $chosen, SchoolDay $day): Response
    {
        $period = $chosen->forRequest($request->integer('periode'));

        $search = trim((string) $request->query('cari', ''));
        $pathId = (string) $request->query('jalur', '');
        $status = (string) $request->query('status', '');

        $paginator = $period === null ? null : Applicant::query()
            ->where('period_id', $period->id)
            ->when($search !== '', fn ($query) => $query->where(
                fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('number', 'like', "%{$search}%"),
            ))
            ->when($pathId !== '', fn ($query) => $query->where('path_id', (int) $pathId))
            ->when(in_array($status, ApplicantStatus::values(), true), fn ($query) => $query->where('status', $status))
            ->orderBy('number')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $pathNames = $period === null ? collect() : $period->paths->pluck('name', 'id');

        return Inertia::render('Ppdb/Applicants', [
            'period' => $period === null ? null : ['id' => $period->id, 'name' => $period->name],
            'showOrigin' => $period === null || $this->rules->builtinStates($period)['origin_school'] !== 'off',
            'applicants' => $paginator === null ? [] : collect($paginator->items())->map(fn (Applicant $applicant): array => [
                'id' => $applicant->id,
                'number' => $applicant->number,
                'name' => $applicant->name,
                'origin' => $applicant->origin_school,
                'pathName' => $pathNames->get($applicant->path_id),
                'status' => $applicant->decision === Decision::Pending ? $applicant->status->value : $applicant->decision->value,
                'registeredOn' => $day->label($applicant->registered_on),
                'source' => $applicant->source->value,
            ])->values()->all(),
            'pagination' => $this->paginationMeta($paginator),
            'filters' => ['cari' => $search, 'jalur' => $pathId, 'status' => $status],
            'paths' => $pathNames->map(fn (string $name, int $id): array => ['value' => (string) $id, 'label' => $name])->values()->all(),
            'statuses' => array_map(
                fn (ApplicantStatus $item): array => ['value' => $item->value, 'label' => $item->label()],
                ApplicantStatus::cases(),
            ),
            'can' => ['manage' => Gate::allows('ppdb.applicants.manage')],
        ]);
    }

    public function create(SchoolDay $day): Response
    {
        $period = AdmissionPeriod::active();

        return Inertia::render('Ppdb/ApplicantForm', [
            'period' => $period === null ? null : ['id' => $period->id, 'name' => $period->name],
            'waves' => $period === null ? [] : $this->waveOptions($period, $day),
            'paths' => $period === null ? [] : $this->pathOptions($period),
            'fields' => $period === null ? [] : $this->form->for($period),
        ]);
    }

    public function store(ApplicantRequest $request, RegisterApplicant $register): RedirectResponse
    {
        $period = AdmissionPeriod::active();

        if ($period === null) {
            throw ValidationException::withMessages(['period' => 'Belum ada periode PPDB yang berjalan.']);
        }

        $userId = Auth::id();

        $applicant = $register->handle($period, $request->validated(), ApplicantSource::Staff, $userId === null ? null : (int) $userId, null, $request->answersData());

        return redirect()
            ->route('ppdb.applicants.show', $applicant)
            ->with('status', "Pendaftar ditambahkan dengan nomor {$applicant->number}.");
    }

    public function show(Applicant $applicant, SchoolDay $day): Response
    {
        $period = AdmissionPeriod::query()->findOrFail($applicant->period_id);
        $wave = AdmissionWave::query()->find($applicant->wave_id);
        $path = AdmissionPath::query()->find($applicant->path_id);

        return Inertia::render('Ppdb/ApplicantShow', [
            'applicant' => [
                'id' => $applicant->id,
                'number' => $applicant->number,
                'name' => $applicant->name,
                'gender' => $applicant->gender,
                'birthPlace' => $applicant->birth_place,
                'birthDate' => $applicant->birth_date,
                'nisn' => $applicant->nisn,
                'originSchool' => $applicant->origin_school,
                'address' => $applicant->address,
                'guardianName' => $applicant->guardian_name,
                'guardianPhone' => $applicant->guardian_phone,
                'waveId' => $applicant->wave_id,
                'waveName' => $wave?->name,
                'pathId' => $applicant->path_id,
                'pathName' => $path?->name,
                'source' => $applicant->source->value,
                'sourceLabel' => $applicant->source->label(),
                'registeredOn' => $day->label($applicant->registered_on),
                'status' => $applicant->status->value,
                'verificationNote' => $applicant->verification_note,
                'score' => $applicant->score === null ? null : number_format((float) $applicant->score, 2, '.', ''),
                'decision' => $applicant->decision->value,
                'decisionLabel' => $applicant->decision->label(),
                'enrolled' => $applicant->isEnrolled(),
                'enrolledOn' => $applicant->enrolled_at === null ? null : $day->label($day->dateOf($applicant->enrolled_at)),
            ],
            'period' => ['id' => $period->id, 'name' => $period->name],
            'waves' => $this->waveOptions($period, $day),
            'paths' => $this->pathOptions($period),
            'fields' => $this->form->for($period),
            'answers' => $this->form->answersOf($applicant),
            'files' => $this->form->filesOf($applicant, fn (FormField $field): string => route('ppdb.applicants.file', ['applicant' => $applicant->id, 'field' => $field->id])),
            'statuses' => array_map(
                fn (ApplicantStatus $item): array => ['value' => $item->value, 'label' => $item->label()],
                ApplicantStatus::cases(),
            ),
            'can' => [
                'manage' => Gate::allows('ppdb.applicants.manage'),
                'cancel' => $applicant->decision === Decision::Pending && ! $applicant->isEnrolled(),
                // Re-registration needs the announcement first: an accepted applicant, once.
                'enroll' => $period->results_published_at !== null && $applicant->decision === Decision::Accepted && ! $applicant->isEnrolled(),
            ],
        ]);
    }

    public function update(ApplicantRequest $request, Applicant $applicant, UpdateApplicant $update): RedirectResponse
    {
        $update->handle($applicant, $request->validated(), $request->answersData());

        return back()->with('status', 'Data pendaftar disimpan.');
    }

    public function destroy(Applicant $applicant, CancelApplication $cancel): RedirectResponse
    {
        $cancel->handle($applicant);

        return redirect()
            ->route('ppdb.applicants')
            ->with('status', "Pendaftaran {$applicant->number} dibatalkan.");
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function waveOptions(AdmissionPeriod $period, SchoolDay $day): array
    {
        return array_values($period->waves->map(fn (AdmissionWave $wave): array => [
            'value' => (string) $wave->id,
            'label' => "{$wave->name} ({$day->label($wave->opens_on)} – {$day->label($wave->closes_on)})",
        ])->all());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function pathOptions(AdmissionPeriod $period): array
    {
        return array_values($period->paths->map(fn (AdmissionPath $path): array => [
            'value' => (string) $path->id,
            'label' => $path->name,
        ])->all());
    }

    /**
     * @param  LengthAwarePaginator<int, Applicant>|null  $paginator
     * @return array{page: int, lastPage: int, total: int, from: int, to: int}
     */
    private function paginationMeta(?LengthAwarePaginator $paginator): array
    {
        if ($paginator === null) {
            return ['page' => 1, 'lastPage' => 1, 'total' => 0, 'from' => 0, 'to' => 0];
        }

        return [
            'page' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'from' => (int) $paginator->firstItem(),
            'to' => (int) $paginator->lastItem(),
        ];
    }
}
