<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\ApplicantAnswer;
use Modules\Ppdb\App\Domain\Models\FormField;
use Modules\Ppdb\App\Domain\Support\AnswerFiles;
use Modules\Ppdb\App\Domain\Support\FormRules;
use Throwable;

/**
 * Saves an applicant's answers to the custom fields of their period's form.
 * Only fields that are asked today are taken (an archived field, a field of
 * another period or an id that is not a field is ignored), and a field the
 * sender left out is left as it was. An empty answer removes the stored one.
 * A checkboxes answer is stored as a JSON list.
 *
 * A file field takes an uploaded file: it is written to the school's
 * private storage under a name the server makes, the answer keeps where it
 * is and the name it was sent with, and the file it replaces is removed once
 * the save is committed. A file field sent without a file keeps what it has.
 * Files written for a save that then fails are removed again.
 */
final class SaveAnswers
{
    private const EXTENSIONS = ['pdf' => 'pdf', 'jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png'];

    public function __construct(
        private readonly FormRules $rules,
        private readonly AnswerFiles $files,
    ) {}

    /**
     * @param  array<array-key, mixed>  $answers  field id => answer
     */
    public function handle(Applicant $applicant, AdmissionPeriod $period, array $answers): void
    {
        $written = [];

        try {
            foreach ($this->rules->askedCustomFields($period) as $field) {
                if (! array_key_exists($field->id, $answers) && ! array_key_exists((string) $field->id, $answers)) {
                    continue;
                }

                $answer = $answers[$field->id] ?? $answers[(string) $field->id];
                $match = ['applicant_id' => $applicant->id, 'field_id' => $field->id];

                if ($field->type === FieldType::File) {
                    $this->saveFile($applicant, $field, $answer, $match, $written);

                    continue;
                }

                $stored = $this->storable($field->type, $answer);

                if ($stored === null) {
                    ApplicantAnswer::query()->where($match)->delete();

                    continue;
                }

                ApplicantAnswer::query()->updateOrCreate($match, ['value' => $stored]);
            }
        } catch (Throwable $exception) {
            foreach ($written as $path) {
                $this->files->delete($path);
            }

            throw $exception;
        }
    }

    /**
     * @param  array{applicant_id: int, field_id: int}  $match
     * @param  list<string>  $written  the paths written so far (added to)
     */
    private function saveFile(Applicant $applicant, FormField $field, mixed $answer, array $match, array &$written): void
    {
        if (! $answer instanceof UploadedFile || ! $answer->isValid()) {
            return;
        }

        $previous = $this->files->decode(ApplicantAnswer::query()->where($match)->value('value'));
        $extension = self::EXTENSIONS[strtolower((string) $answer->guessExtension())] ?? 'bin';
        $path = sprintf('applicants/%d/%d-%s.%s', $applicant->id, $field->id, Str::lower(Str::random(20)), $extension);

        $this->files->put($path, (string) $answer->get());
        $written[] = $path;

        ApplicantAnswer::query()->updateOrCreate($match, ['value' => (string) json_encode([
            'path' => $path,
            'name' => $this->safeName($answer->getClientOriginalName()),
            'size' => (int) $answer->getSize(),
            'mime' => (string) $answer->getMimeType(),
        ], JSON_UNESCAPED_UNICODE)]);

        if ($previous !== null) {
            DB::afterCommit(fn () => $this->files->delete($previous['path']));
        }
    }

    /**
     * What the sender called the file, made safe to show and to offer as a
     * download name.
     */
    private function safeName(string $name): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F\\\\\/]+/u', '', basename(str_replace('\\', '/', $name))));

        return $name === '' ? 'berkas' : mb_substr($name, 0, 120);
    }

    private function storable(FieldType $type, mixed $answer): ?string
    {
        if ($type === FieldType::Checkboxes) {
            $chosen = is_array($answer) ? array_values(array_map('strval', $answer)) : [];

            return $chosen === [] ? null : (string) json_encode($chosen, JSON_UNESCAPED_UNICODE);
        }

        $text = is_scalar($answer) ? trim((string) $answer) : '';

        return $text === '' ? null : $text;
    }
}
