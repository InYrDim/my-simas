<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Modules\Platform\App\Domain\Models\Tenant;

use function Pest\Laravel\post;

/*
 * Helpers shared by the import tests: send a CSV to the preview or the
 * import endpoint the way the page does (multipart, JSON answer).
 */

/**
 * @param  list<string>  $lines  the header line followed by the data lines
 * @return TestResponse<JsonResponse>
 */
function sendCsv(Tenant $tenant, string $path, string $target, array $lines, string $mode = 'add'): TestResponse
{
    return post(school($tenant->slug, $path), [
        'target' => $target,
        'mode' => $mode,
        'file' => UploadedFile::fake()->createWithContent('data.csv', implode("\n", $lines)."\n"),
    ], ['Accept' => 'application/json']);
}

/**
 * @param  list<string>  $lines
 * @return TestResponse<JsonResponse>
 */
function previewCsv(Tenant $tenant, string $target, array $lines, string $mode = 'add'): TestResponse
{
    return sendCsv($tenant, '/kelola/impor/pratinjau', $target, $lines, $mode);
}

/**
 * @param  list<string>  $lines
 * @return TestResponse<JsonResponse>
 */
function importCsv(Tenant $tenant, string $target, array $lines, string $mode = 'add'): TestResponse
{
    return sendCsv($tenant, '/kelola/impor', $target, $lines, $mode);
}
