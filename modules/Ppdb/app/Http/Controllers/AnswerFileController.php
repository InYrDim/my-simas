<?php

namespace Modules\Ppdb\App\Http\Controllers;

use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\FormField;
use Modules\Ppdb\App\Domain\Support\AnswerFiles;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads a file an applicant uploaded, for the committee. Both models are
 * read inside the school (tenant scope), and the file is found through the
 * applicant's own answer row, never through a path in the request.
 */
final class AnswerFileController
{
    public function __invoke(Applicant $applicant, FormField $field, AnswerFiles $files): StreamedResponse
    {
        $response = $files->download($applicant, $field);

        abort_if($response === null, 404);

        return $response;
    }
}
