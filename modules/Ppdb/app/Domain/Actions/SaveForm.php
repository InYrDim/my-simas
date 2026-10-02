<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\ApplicantAnswer;
use Modules\Ppdb\App\Domain\Models\FormField;
use Modules\Ppdb\App\Domain\Support\CustomFieldRules;

/**
 * Saves the form a builder page sends for one period, as a whole. The rows
 * are the form IN ORDER: a row with an id changes that field, a row without
 * one adds a custom field, and the order of the rows is the order of the
 * form. Every field of the period must be in the list — a page that was
 * opened before another change would otherwise put fields in the wrong
 * place — and the save is all or nothing.
 *
 * A built-in field only takes its help text, whether it is required and
 * whether it is archived; the path, name and gender are always required and
 * never archived. A custom field takes everything, but its type is locked
 * once someone has answered it. An archived field is no longer asked and
 * keeps its answers. A field is removed by `DeleteFormField`.
 */
final class SaveForm
{
    public const MAX_FIELDS = 60;

    public const MAX_OPTIONS = 30;

    public function __construct(
        private readonly CustomFieldRules $customRules,
        private readonly SeedFormFields $seed,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows  `id`, `type`, `label`, `help`, `required`, `archived`, `options`, `rules`
     *
     * @throws ValidationException
     */
    public function handle(AdmissionPeriod $period, array $rows): void
    {
        if ($period->status === PeriodStatus::Closed) {
            throw ValidationException::withMessages(['form' => 'Formulir periode yang sudah ditutup tidak bisa diubah.']);
        }

        if (count($rows) > self::MAX_FIELDS) {
            throw ValidationException::withMessages(['form' => 'Formulir paling banyak '.self::MAX_FIELDS.' kolom.']);
        }

        $this->seed->ensure($period);

        $existing = $period->fields()->get()->keyBy('id');
        $this->checkCompleteness(array_values(array_map('intval', $existing->keys()->all())), $rows);

        $answered = ApplicantAnswer::query()
            ->whereIn('field_id', $existing->keys()->all())
            ->distinct()
            ->pluck('field_id')
            ->map(fn ($id): int => (int) $id)
            ->flip();

        $changes = [];

        foreach ($rows as $index => $row) {
            $id = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            $field = $id === null ? null : $existing->get($id);

            $changes[] = [
                'field' => $field,
                'attributes' => $field !== null && $field->isBuiltin()
                    ? $this->builtinChanges($field, $row, $index)
                    : $this->customChanges($field, $row, $index, $field !== null && $answered->has($field->id)),
            ];
        }

        DB::transaction(function () use ($period, $changes): void {
            foreach ($changes as $order => $change) {
                $attributes = [...$change['attributes'], 'sort_order' => $order];

                if ($change['field'] === null) {
                    FormField::query()->create([...$attributes, 'period_id' => $period->id, 'key' => null]);

                    continue;
                }

                $change['field']->fill($attributes)->save();
            }
        });
    }

    /**
     * @param  list<int>  $existingIds
     * @param  list<array<string, mixed>>  $rows
     *
     * @throws ValidationException
     */
    private function checkCompleteness(array $existingIds, array $rows): void
    {
        $sent = [];

        foreach ($rows as $index => $row) {
            if (! isset($row['id']) || $row['id'] === '') {
                continue;
            }

            $id = (int) $row['id'];

            if (! in_array($id, $existingIds, true) || in_array($id, $sent, true)) {
                throw ValidationException::withMessages(["fields.{$index}.id" => 'Kolom tidak ditemukan di periode ini.']);
            }

            $sent[] = $id;
        }

        if (array_diff($existingIds, $sent) !== []) {
            throw ValidationException::withMessages([
                'form' => 'Susunan formulir sudah berubah di tempat lain. Muat ulang halaman, lalu ulangi perubahan Anda.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function builtinChanges(FormField $field, array $row, int $index): array
    {
        $archived = filter_var($row['archived'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($field->isLocked() && $archived) {
            throw ValidationException::withMessages(["fields.{$index}.archived" => 'Isian ini selalu diminta dan tidak bisa diarsipkan.']);
        }

        return [
            'help' => $this->help($row),
            'required' => $field->isLocked() || filter_var($row['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'archived_at' => $archived ? ($field->archived_at ?? now()) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function customChanges(?FormField $field, array $row, int $index, bool $hasAnswers): array
    {
        $type = FieldType::tryFrom((string) ($row['type'] ?? ''));

        if ($type === null || $type === FieldType::Builtin) {
            throw ValidationException::withMessages(["fields.{$index}.type" => 'Tipe kolom tidak valid.']);
        }

        if ($field !== null && $hasAnswers && $type !== $field->type) {
            throw ValidationException::withMessages(["fields.{$index}.type" => 'Tipe tidak bisa diubah karena kolom ini sudah punya jawaban. Arsipkan kolom ini lalu buat yang baru.']);
        }

        $label = trim((string) ($row['label'] ?? ''));

        if ($label === '') {
            throw ValidationException::withMessages(["fields.{$index}.label" => 'Label wajib diisi.']);
        }

        $rules = $row['rules'] ?? [];

        return [
            'type' => $type,
            'label' => $label,
            'help' => $this->help($row),
            'required' => $type->takesAnswer() && filter_var($row['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'options' => $type->hasOptions() ? $this->options($row['options'] ?? [], $index) : null,
            'rules' => $this->customRules->normalise($type, is_array($rules) ? $rules : [], "fields.{$index}"),
            'archived_at' => filter_var($row['archived'] ?? false, FILTER_VALIDATE_BOOLEAN)
                ? ($field === null ? now() : ($field->archived_at ?? now()))
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function help(array $row): ?string
    {
        $help = trim((string) ($row['help'] ?? ''));

        return $help === '' ? null : $help;
    }

    /**
     * @return list<string>
     *
     * @throws ValidationException
     */
    private function options(mixed $input, int $index): array
    {
        $options = [];
        $seen = [];

        foreach (is_array($input) ? $input : [] as $option) {
            $text = trim((string) $option);
            $key = mb_strtolower($text);

            if ($text === '') {
                continue;
            }

            if (in_array($key, $seen, true)) {
                throw ValidationException::withMessages(["fields.{$index}.options" => 'Opsi jawaban tidak boleh ganda.']);
            }

            $seen[] = $key;
            $options[] = $text;
        }

        if ($options === []) {
            throw ValidationException::withMessages(["fields.{$index}.options" => 'Tambahkan setidaknya satu opsi jawaban.']);
        }

        if (count($options) > self::MAX_OPTIONS) {
            throw ValidationException::withMessages(["fields.{$index}.options" => 'Opsi jawaban paling banyak '.self::MAX_OPTIONS.'.']);
        }

        return $options;
    }
}
