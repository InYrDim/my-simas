<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Enums\FieldRequirement;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Support\FormFields;

/**
 * Saves which fields of the registration form a period asks for. Turning a
 * field off only takes it off the form: what applicants already filled in
 * stays on their record.
 */
final class SaveFormFields
{
    /**
     * @param  array<string, string>  $fields  requirement by field key
     *
     * @throws ValidationException when a field is not one a school may adjust
     */
    public function handle(AdmissionPeriod $period, array $fields): AdmissionPeriod
    {
        $stored = [];

        foreach (FormFields::keys() as $key) {
            $requirement = FieldRequirement::tryFrom($fields[$key] ?? '');

            if ($requirement === null) {
                throw ValidationException::withMessages(["fields.{$key}" => FormFields::label($key).' tidak valid.']);
            }

            $stored[$key] = $requirement->value;
        }

        $period->forceFill(['form_fields' => $stored])->save();

        return $period;
    }
}
