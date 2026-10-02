<?php

namespace Modules\Ppdb\App\Domain\Support;

use Illuminate\Support\Facades\Storage;
use Modules\Platform\App\Contracts\TenantStorage;
use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\ApplicantAnswer;
use Modules\Ppdb\App\Domain\Models\FormField;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The files applicants upload for file fields: kept in the school's own
 * private storage partition (`TenantStorage`, module `ppdb`), named by the
 * server, never by the sender. The answer row holds where the file is and
 * the name it was sent with, as JSON (`path`, `name`, `size`, `mime`). A
 * path is only ever read from an answer row, never from a request.
 */
final class AnswerFiles
{
    public const MODULE = 'ppdb';

    public function __construct(
        private readonly TenantStorage $storage,
    ) {}

    /**
     * @return array{path: string, name: string, size: int, mime: string}|null
     */
    public function decode(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $data = json_decode($value, true);

        if (! is_array($data) || ! isset($data['path'], $data['name']) || ! is_string($data['path']) || ! is_string($data['name'])) {
            return null;
        }

        return [
            'path' => $data['path'],
            'name' => $data['name'],
            'size' => (int) ($data['size'] ?? 0),
            'mime' => is_string($data['mime'] ?? null) ? $data['mime'] : 'application/octet-stream',
        ];
    }

    public function put(string $relative, string $contents): void
    {
        $this->storage->put(self::MODULE, $relative, $contents);
    }

    public function delete(string $relative): void
    {
        $this->storage->delete(self::MODULE, $relative);
    }

    /**
     * The stored answer of the applicant to a file field, if any.
     *
     * @return array{path: string, name: string, size: int, mime: string}|null
     */
    public function of(Applicant $applicant, FormField $field): ?array
    {
        if ($field->type !== FieldType::File || $field->period_id !== $applicant->period_id) {
            return null;
        }

        $value = ApplicantAnswer::query()
            ->where('applicant_id', $applicant->id)
            ->where('field_id', $field->id)
            ->value('value');

        return $this->decode(is_string($value) ? $value : null);
    }

    /**
     * Every file path the applicant has uploaded.
     *
     * @return list<string>
     */
    public function pathsOf(Applicant $applicant): array
    {
        $fileFields = FormField::query()
            ->where('period_id', $applicant->period_id)
            ->where('type', FieldType::File->value)
            ->pluck('id');

        $paths = [];

        foreach (ApplicantAnswer::query()->where('applicant_id', $applicant->id)->whereIn('field_id', $fileFields->all())->get() as $answer) {
            $stored = $this->decode($answer->value);

            if ($stored !== null) {
                $paths[] = $stored['path'];
            }
        }

        return $paths;
    }

    /**
     * The file as a download (never shown inline), or null when there is
     * none or it is gone from the disk.
     */
    public function download(Applicant $applicant, FormField $field): ?StreamedResponse
    {
        $stored = $this->of($applicant, $field);

        if ($stored === null || ! $this->storage->exists(self::MODULE, $stored['path'])) {
            return null;
        }

        return Storage::disk('local')->download(
            $this->storage->path(self::MODULE, $stored['path']),
            $stored['name'],
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }
}
