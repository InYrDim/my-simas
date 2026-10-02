<?php

namespace Modules\Ppdb\App\Http\Requests\Account;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Ppdb\App\Http\Requests\PpdbFormRequest;

final class RegisterAccountRequest extends PpdbFormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('ppdb_accounts', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'Nama', 'email' => 'Email', 'password' => 'Kata sandi'];
    }

    /**
     * The validated fields as the action takes them.
     *
     * @return array{name: string, email: string, password: string}
     */
    public function accountData(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'email' => (string) $this->validated('email'),
            'password' => (string) $this->validated('password'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'email.email' => 'Email harus berupa alamat email yang valid.',
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal :min karakter.',
        ];
    }
}
