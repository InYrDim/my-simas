<?php

namespace Modules\Ppdb\App\Http\Controllers\Account;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ppdb\App\Domain\Actions\Account\JoinSchool;
use Modules\Ppdb\App\Domain\Actions\Account\LeaveSchool;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;
use Modules\Ppdb\App\Http\Requests\Account\JoinSchoolRequest;

/**
 * Joining and leaving a school from the applicant's own page.
 *
 * Guessing codes is what this form invites, so refused attempts are
 * counted: ten in ten minutes per account and address, then it waits.
 * The school code comes from the form or from the link the school hands
 * out (`?school=`) — it fills the field, nothing more.
 */
final class JoinSchoolController
{
    private const MAX_REFUSALS = 10;

    private const DECAY_SECONDS = 600;

    public function show(Request $request): Response|RedirectResponse
    {
        /** @var PpdbAccount $account */
        $account = Auth::guard('ppdb')->user();

        if ($account->tenant_id !== null) {
            return redirect()
                ->route('ppdb.account.home')
                ->with('status', 'Anda sudah bergabung ke sebuah sekolah. Keluar dari sekolah itu lebih dulu bila ingin pindah.');
        }

        return Inertia::render('Ppdb/Account/Join', [
            'code' => mb_strtolower(trim((string) $request->query('school', ''))),
        ]);
    }

    public function store(JoinSchoolRequest $request, JoinSchool $join): RedirectResponse
    {
        /** @var PpdbAccount $account */
        $account = Auth::guard('ppdb')->user();
        $key = sprintf('ppdb-join:%d:%s', $account->id, $request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_REFUSALS)) {
            throw ValidationException::withMessages([
                'code' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
            ]);
        }

        try {
            $join->handle($account, $request->validated('code'));
        } catch (ValidationException $exception) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw $exception;
        }

        return redirect()->route('ppdb.account.home')->with('status', 'Anda sudah bergabung ke sekolah.');
    }

    public function destroy(LeaveSchool $leave): RedirectResponse
    {
        /** @var PpdbAccount $account */
        $account = Auth::guard('ppdb')->user();

        $leave->handle($account);

        return redirect()->route('ppdb.account.home')->with('status', 'Anda keluar dari sekolah.');
    }
}
