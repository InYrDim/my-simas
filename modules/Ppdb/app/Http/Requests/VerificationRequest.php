<?php

namespace Modules\Ppdb\App\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;

final class VerificationRequest extends PpdbFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(ApplicantStatus::values())],
            'verification_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['status' => 'Status', 'verification_note' => 'Catatan'];
    }
}
