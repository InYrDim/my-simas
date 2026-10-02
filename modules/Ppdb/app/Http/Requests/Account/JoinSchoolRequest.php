<?php

namespace Modules\Ppdb\App\Http\Requests\Account;

use Modules\Ppdb\App\Http\Requests\PpdbFormRequest;

final class JoinSchoolRequest extends PpdbFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => mb_strtolower(trim((string) $this->input('code')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:64']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => 'Kode sekolah'];
    }
}
