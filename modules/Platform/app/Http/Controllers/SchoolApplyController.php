<?php

namespace Modules\Platform\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Contracts\Exceptions\InvalidApplicationException;
use Modules\Platform\App\Contracts\TenantApplications;

/**
 * Public school application form (Fase 2 Stage 7): CENTRAL hosts only,
 * no auth — the applicant is not a user anywhere yet. Anti-spam without
 * dependencies: IP throttle on the routes (safe here — no tenant context
 * needed) plus a hidden honeypot field; a filled honeypot is rejected
 * SILENTLY (generic success, no row) so bots learn nothing.
 *
 * Domain rules (slug shape/reserved/taken, duplicate pending
 * application/email) live in the TenantApplications contract; this
 * controller only translates HTTP ⇄ contract.
 */
final class SchoolApplyController
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

    /**
     * The application form.
     */
    public function create(): Response
    {
        return Inertia::render('Platform/SchoolApply', [
            'timezones' => self::TIMEZONES,
        ]);
    }

    /**
     * Handle a submission. Honeypot first (silent), then HTTP-level
     * validation, then the contract's full rule set.
     */
    public function store(Request $request): RedirectResponse
    {
        // Honeypot: humans never see (or fill) this field. Pretend
        // success — no row, no error hint for the bot.
        if ($request->filled('website')) {
            return redirect()
                ->route('school.apply.create')
                ->with('status', 'Pengajuan terkirim — mohon tunggu ACC dari provider.');
        }

        $validated = $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'desired_slug' => ['required', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:255'],
            'applicant_name' => ['required', 'string', 'max:255'],
            'applicant_email' => ['required', 'email', 'max:255'],
            'applicant_message' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->applications->submit($validated);
        } catch (InvalidApplicationException $e) {
            return back()->withErrors([
                'application' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('school.apply.create')
            ->with('status', 'Pengajuan terkirim — mohon tunggu ACC dari provider.');
    }
}
