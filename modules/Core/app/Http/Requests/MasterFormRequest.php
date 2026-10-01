<?php

namespace Modules\Core\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Base for every master-data write: gated by `core.master.manage` and
 * validated with Indonesian messages (the app locale ships none).
 */
abstract class MasterFormRequest extends FormRequest
{
    /**
     * Sentinel a select sends for "no choice" (Radix items cannot carry
     * an empty value); converted to null before validation.
     */
    public const NONE = 'none';

    /**
     * Optional select fields whose NONE sentinel becomes null.
     *
     * @var list<string>
     */
    protected array $optionalSelects = [];

    public function authorize(): bool
    {
        return Gate::allows('core.master.manage');
    }

    protected function prepareForValidation(): void
    {
        foreach ($this->optionalSelects as $field) {
            if ($this->input($field) === self::NONE || $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * Unique within the current school (Rule::unique ignores tenant scope).
     */
    protected function uniqueInSchool(string $table, string $column, ?int $ignoreId = null): Unique
    {
        return Rule::unique($table, $column)
            ->where('tenant_id', app(TenantContext::class)->currentOrFail()->id)
            ->ignore($ignoreId);
    }

    /**
     * An id that exists in the current school's table.
     */
    protected function existsInSchool(string $table): Exists
    {
        return Rule::exists($table, 'id')
            ->where('tenant_id', app(TenantContext::class)->currentOrFail()->id);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'max' => ':attribute terlalu panjang (maksimal :max).',
            'min' => ':attribute minimal :min.',
            'email' => ':attribute harus berupa alamat email yang valid.',
            'date' => ':attribute bukan tanggal yang valid.',
            'date_format' => ':attribute bukan tanggal yang valid.',
            'after' => ':attribute harus setelah tanggal mulai.',
            'after_or_equal' => ':attribute tidak boleh sebelum tanggal mulai.',
            'before_or_equal' => ':attribute tidak boleh melewati hari ini.',
            'in' => ':attribute tidak valid.',
            'integer' => ':attribute harus berupa angka bulat.',
            'numeric' => ':attribute harus berupa angka.',
            'between' => ':attribute harus antara :min dan :max.',
            'unique' => ':attribute sudah dipakai.',
            'exists' => ':attribute tidak ditemukan.',
            'array' => ':attribute tidak valid.',
            'regex' => 'Format :attribute tidak valid.',
        ];
    }
}
