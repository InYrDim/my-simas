<?php

namespace Modules\Core\App\Http\Requests;

final class ExtracurricularMemberRequest extends MasterFormRequest
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
