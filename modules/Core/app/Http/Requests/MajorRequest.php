<?php

namespace Modules\Core\App\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\App\Domain\Models\Major;

final class MajorRequest extends MasterFormRequest
{
    protected function prepareForValidation(): void
    {
        $concentrations = $this->input('concentrations');

        if (is_string($concentrations)) {
            $this->merge(['concentrations' => array_values(array_filter(
                array_map('trim', explode(',', $concentrations)),
                fn (string $item): bool => $item !== '',
            ))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Major|null $major */
        $major = $this->route('major');

        return [
            'code' => ['required', 'string', 'max:16', $this->uniqueInSchool('majors', 'code', $major?->id)],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(['Peminatan', 'Kompetensi Keahlian'])],
            'concentrations' => ['nullable', 'array'],
            'concentrations.*' => ['string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'Kode',
            'name' => 'Nama',
            'kind' => 'Jenis',
            'concentrations' => 'Konsentrasi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...parent::messages(), 'code.unique' => 'Kode jurusan ini sudah dipakai.'];
    }

    /**
     * The validated fields as the action takes them; concentrations are
     * present only when the form sent them.
     *
     * @return array{code: string, name: string, kind: string, concentrations?: list<string>}
     */
    public function majorData(): array
    {
        $validated = $this->validated();

        $data = [
            'code' => (string) $validated['code'],
            'name' => (string) $validated['name'],
            'kind' => (string) $validated['kind'],
        ];

        if (is_array($validated['concentrations'] ?? null)) {
            $data['concentrations'] = array_values(array_map(strval(...), $validated['concentrations']));
        }

        return $data;
    }
}
