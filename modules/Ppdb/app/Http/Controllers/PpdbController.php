<?php

namespace Modules\Ppdb\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Ppdb\App\Infrastructure\Mock\PpdbMockData;

/**
 * PPDB mockup pages: admissions overview, applicant list, selection and
 * announcement.
 *
 * Mock phase: every page reads static sample data from PpdbMockData and
 * nothing is persisted. The DB phase replaces the mock behind this
 * controller; the page props stay the same.
 */
final class PpdbController
{
    public function overview(): Response
    {
        $data = new PpdbMockData;

        return Inertia::render('Ppdb/Overview', [
            'period' => $data->period(),
            'funnel' => $data->funnel(),
            'waves' => $data->waves(),
        ]);
    }

    public function applicants(): Response
    {
        $data = new PpdbMockData;

        return Inertia::render('Ppdb/Applicants', [
            'applicants' => $data->applicants(),
            'paths' => $data->paths(),
        ]);
    }

    public function selection(): Response
    {
        $data = new PpdbMockData;

        return Inertia::render('Ppdb/Selection', [
            'quota' => $data->quota(),
            'candidates' => $data->candidates(),
        ]);
    }
}
