<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\ApplicantAnswer;
use Modules\Ppdb\App\Domain\Models\FormField;

/**
 * Removes a custom field. Only while nobody has answered it: a field with
 * answers is archived instead, so what applicants gave is never lost. A
 * built-in field is never removed.
 */
final class DeleteFormField
{
    /**
     * @throws ValidationException
     */
    public function handle(FormField $field): void
    {
        if ($field->isBuiltin()) {
            throw ValidationException::withMessages(['form' => 'Isian bawaan tidak bisa dihapus; arsipkan bila tidak dipakai.']);
        }

        $period = AdmissionPeriod::query()->findOrFail($field->period_id);

        if ($period->status === PeriodStatus::Closed) {
            throw ValidationException::withMessages(['form' => 'Formulir periode yang sudah ditutup tidak bisa diubah.']);
        }

        if (ApplicantAnswer::query()->where('field_id', $field->id)->exists()) {
            throw ValidationException::withMessages(['form' => 'Kolom ini sudah punya jawaban, jadi tidak bisa dihapus. Arsipkan saja.']);
        }

        $field->delete();
    }
}
