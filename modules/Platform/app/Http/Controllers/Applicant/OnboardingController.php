<?php

namespace Modules\Platform\App\Http\Controllers\Applicant;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Contracts\Exceptions\ApplicationNotPendingException;
use Modules\Platform\App\Contracts\Exceptions\InvalidApplicationException;
use Modules\Platform\App\Contracts\TenantApplications;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Http\Support\ConsoleResources;

/**
 * The applicant's onboarding page: fill in the school, choose a plan and
 * submit for provider review, then follow the status. A rejected
 * application is corrected and resubmitted on the same row.
 *
 * Domain rules (one application per applicant, slug and plan checks)
 * live in the TenantApplications contract; this controller only
 * translates HTTP ⇄ contract. The applicant's identity always comes
 * from the session, never from the form.
 */
final class OnboardingController
{
    /**
     * Timezone choices for the form (Indonesia's three IANA zones).
     *
     * @var array<int, string>
     */
    private const TIMEZONES = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'];

    public function __construct(
        private readonly TenantApplications $applications,
    ) {}

    public function show(): Response
    {
        $applicant = $this->applicant();

        return Inertia::render('Platform/Applicant/Onboarding', [
            'applicant' => ['name' => $applicant->name, 'email' => $applicant->email],
            'timezones' => self::TIMEZONES,
            'plans' => $this->plans(),
            'trialDays' => (int) config('billing.trial_days', 14),
            'application' => $this->applications->forApplicant($applicant->id),
        ]);
    }

    /**
     * First submission.
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $this->applications->submit($this->applicant()->id, $this->validated($request));
        } catch (InvalidApplicationException $e) {
            return back()->withErrors(['application' => $e->getMessage()]);
        }

        return redirect()
            ->route('applicant.home')
            ->with('status', 'Pengajuan terkirim — mohon tunggu ACC dari provider.');
    }

    /**
     * Resubmission of a rejected application.
     */
    public function update(Request $request): RedirectResponse
    {
        try {
            $this->applications->resubmit($this->applicant()->id, $this->validated($request));
        } catch (InvalidApplicationException|ApplicationNotPendingException $e) {
            return back()->withErrors(['application' => $e->getMessage()]);
        }

        return redirect()
            ->route('applicant.home')
            ->with('status', 'Pengajuan dikirim ulang — mohon tunggu ACC dari provider.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'desired_slug' => ['required', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:255'],
            'plan_key' => ['required', 'string', 'max:255'],
            'applicant_message' => ['nullable', 'string', 'max:2000'],
        ], [
            'school_name.required' => 'Nama sekolah wajib diisi.',
            'desired_slug.required' => 'Kode sekolah wajib diisi.',
            'plan_key.required' => 'Pilih salah satu paket.',
        ]);
    }

    /**
     * The plans an applicant may choose from, cheapest first as ordered
     * by the provider.
     *
     * @return list<array{key: string, name: string, priceMonthly: int, priceYearly: int, limits: array{students: int|null, staffAccounts: int|null, storageMb: int|null}, modules: array<int, string>}>
     */
    private function plans(): array
    {
        return array_values(Plan::query()
            ->selectable()
            ->public()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan): array => [
                'key' => $plan->key,
                'name' => $plan->name,
                'priceMonthly' => $plan->price_monthly,
                'priceYearly' => $plan->price_yearly,
                'limits' => ConsoleResources::limits($plan),
                'modules' => $plan->modules,
            ])
            ->all());
    }

    private function applicant(): Applicant
    {
        /** @var Applicant $applicant */
        $applicant = Auth::guard('applicant')->user();

        return $applicant;
    }
}
