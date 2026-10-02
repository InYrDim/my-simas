<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Modules\Core\App\Domain\Actions\CheckStudentImport;
use Modules\Core\App\Domain\Actions\CheckTeacherImport;
use Modules\Core\App\Domain\Actions\ImportStudents;
use Modules\Core\App\Domain\Actions\ImportTeachers;
use Modules\Core\App\Domain\Enums\ImportTarget;
use Modules\Core\App\Domain\Exceptions\CsvFormatException;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Requests\ImportRequest;
use Modules\Core\App\Infrastructure\Csv\CsvReader;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Impor Data: students and teachers brought in from CSV in three steps —
 * upload, preview, confirm. Nothing is kept between the steps: the browser
 * sends the file for the preview and again to import it, and the import
 * checks every row anew.
 */
final class ImportController
{
    use RendersMasterPage;

    public function index(): Response
    {
        return $this->renderMaster('Core/Manage/Import/Index', [
            'targets' => array_map(fn (ImportTarget $target): array => [
                'value' => $target->value,
                'label' => $target->label(),
                'columns' => $target->columns(),
                'required' => $target->requiredColumns(),
            ], ImportTarget::cases()),
            'maxRows' => CsvReader::MAX_ROWS,
        ]);
    }

    /**
     * The CSV template of a target: its column names and one example row.
     * The `sep=` line makes spreadsheet programs split the columns
     * whatever the computer's regional settings are.
     */
    public function template(ImportTarget $target): StreamedResponse
    {
        return response()->streamDownload(function () use ($target): void {
            echo "sep=;\r\n";
            echo implode(';', $target->columns())."\r\n";
            echo implode(';', $target->sampleRow())."\r\n";
        }, $target->templateFileName(), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * What importing the file would do, row by row. Writes nothing.
     */
    public function preview(ImportRequest $request, CsvReader $reader, CheckStudentImport $students, CheckTeacherImport $teachers): JsonResponse
    {
        $rows = $this->rows($request, $reader);

        $checked = match ($request->target()) {
            ImportTarget::Students => $students->handle($rows, $request->mode()),
            ImportTarget::Teachers => $teachers->handle($rows, $request->mode()),
        };

        $outcomes = array_count_values(array_column($checked, 'outcome'));

        return response()->json([
            'summary' => [
                'total' => count($checked),
                'create' => $outcomes['create'] ?? 0,
                'update' => $outcomes['update'] ?? 0,
                'error' => $outcomes['error'] ?? 0,
            ],
            'rows' => array_map(fn (array $row): array => [
                'line' => $row['line'],
                'key' => $row['key'],
                'name' => $row['name'],
                'detail' => $row['detail'],
                'outcome' => $row['outcome'],
                'messages' => $row['messages'],
            ], $checked),
        ]);
    }

    public function store(ImportRequest $request, CsvReader $reader, ImportStudents $students, ImportTeachers $teachers): JsonResponse
    {
        $rows = $this->rows($request, $reader);

        return response()->json(match ($request->target()) {
            ImportTarget::Students => $students->handle($rows, $request->mode()),
            ImportTarget::Teachers => $teachers->handle($rows, $request->mode()),
        });
    }

    /**
     * The rows of the uploaded file; a file that cannot be read as a
     * whole becomes a validation error on the file field.
     *
     * @return list<array{line: int, values: array<string, string>}>
     */
    private function rows(ImportRequest $request, CsvReader $reader): array
    {
        try {
            return $reader->read((string) $request->file('file')->getRealPath(), $request->target()->requiredColumns());
        } catch (CsvFormatException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }
    }
}
