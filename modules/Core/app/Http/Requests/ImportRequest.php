<?php

namespace Modules\Core\App\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\App\Domain\Enums\ImportMode;
use Modules\Core\App\Domain\Enums\ImportTarget;

final class ImportRequest extends MasterFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target' => ['required', Rule::enum(ImportTarget::class)],
            'mode' => ['required', Rule::enum(ImportMode::class)],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ];
    }

    public function target(): ImportTarget
    {
        return ImportTarget::from($this->validated('target'));
    }

    public function mode(): ImportMode
    {
        return ImportMode::from($this->validated('mode'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'target' => 'Jenis data',
            'mode' => 'Mode impor',
            'file' => 'Berkas CSV',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'enum' => ':attribute tidak valid.',
            'file.required' => 'Pilih berkas CSV terlebih dahulu.',
            'file.file' => 'Berkas tidak bisa dibaca.',
            'file.uploaded' => 'Berkas gagal diunggah.',
            'file.mimes' => 'Berkas harus berupa CSV.',
            'file.max' => 'Ukuran berkas maksimal 1 MB.',
        ];
    }
}
