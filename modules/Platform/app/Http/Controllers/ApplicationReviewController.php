<?php

namespace Modules\Platform\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Contracts\Exceptions\ApplicationNotPendingException;
use Modules\Platform\App\Contracts\Exceptions\InvalidApplicationException;
use Modules\Platform\App\Contracts\TenantApplications;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\ProviderUser;
use Modules\Platform\App\Domain\Models\TenantApplication;
use Modules\Platform\App\Infrastructure\Onboarding\ApplicationDataMapper;

/**
 * Provider console: review school applications. The provider may
 * correct school name/slug/timezone from the approve form — the
 * corrected payload (school data and plan) becomes the final tenant
 * data (grill Q9). Domain
 * rules live in the TenantApplications contract; this controller only
 * translates HTTP ⇄ contract.
 */
final class ApplicationReviewController
{
    public function __construct(
        private readonly TenantApplications $applications,
    ) {}

    /**
     * Pending applications queue (oldest first).
     */
    public function index(): Response
    {
        return Inertia::render('Platform/Applications/Index', [
            'applications' => $this->applications->pending(),
        ]);
    }

    /**
     * Review detail: one application. Decided rows render read-only on
     * the page; the pending queue (index) is the working list.
     */
    public function show(TenantApplication $application): Response
    {
        return Inertia::render('Platform/Applications/Show', [
            'application' => ApplicationDataMapper::map($application),
            'plans' => Plan::query()->selectable()->orderBy('sort_order')->get()
                ->map(fn (Plan $plan): array => ['key' => $plan->key, 'name' => $plan->name])
                ->all(),
        ]);
    }

    /**
     * Approve with (optionally corrected) school data.
     */
    public function approve(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'desired_slug' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:255'],
            'plan_key' => ['nullable', 'string', 'max:255'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $applicationId = (int) $request->route('application');

        /** @var ProviderUser $provider */
        $provider = $request->user('provider');

        try {
            $this->applications->approve(
                $applicationId,
                (int) $provider->id,
                [
                    'school_name' => $validated['school_name'],
                    'desired_slug' => $validated['desired_slug'],
                    'timezone' => $validated['timezone'],
                    'plan_key' => $validated['plan_key'] ?? null,
                    'admin_note' => $validated['admin_note'] ?? null,
                ],
            );
        } catch (InvalidApplicationException|ApplicationNotPendingException $e) {
            return back()->withErrors([
                'application' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('platform.applications.index')
            ->with('status', 'Aplikasi disetujui — sekolah berhasil dibuat.');
    }

    /**
     * Reject with an optional note (confirm collected client-side).
     */
    public function reject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $applicationId = (int) $request->route('application');

        /** @var ProviderUser $provider */
        $provider = $request->user('provider');

        try {
            $this->applications->reject(
                $applicationId,
                $validated['admin_note'] ?? null,
                (int) $provider->id,
            );
        } catch (ApplicationNotPendingException $e) {
            return back()->withErrors([
                'application' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('platform.applications.index')
            ->with('status', 'Aplikasi ditolak.');
    }
}
