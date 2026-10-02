<?php

namespace Modules\Ppdb\App\Http\Requests;

final class EnrollmentRequest extends PpdbFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['nis' => ['required', 'string', 'max:32']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nis' => 'NIS'];
    }
}
