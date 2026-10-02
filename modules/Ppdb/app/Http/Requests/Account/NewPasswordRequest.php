<?php

namespace Modules\Ppdb\App\Http\Requests\Account;

use Illuminate\Validation\Rules\Password;
use Modules\Ppdb\App\Http\Requests\PpdbFormRequest;

final class NewPasswordRequest extends PpdbFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['password' => ['required', 'confirmed', Password::defaults()]];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['password' => 'Kata sandi'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal :min karakter.',
        ];
    }
}
