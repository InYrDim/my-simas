<?php

namespace Modules\Ppdb\App\Http\Requests\Account;

use Modules\Ppdb\App\Http\Requests\PpdbFormRequest;

final class EmailRequest extends PpdbFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'Email'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...parent::messages(), 'email.email' => 'Email harus berupa alamat email yang valid.'];
    }
}
