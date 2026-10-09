<?php

namespace Modules\Attendance\App\Http\Requests;

use Illuminate\Support\Facades\Gate;

/**
 * The picture of the student card: a PNG or JPEG (never SVG, which can
 * carry script), big enough to print.
 */
final class CardImageRequest extends AttendanceFormRequest
{
    public const MAX_KILOBYTES = 5120;

    public function authorize(): bool
    {
        return Gate::allows('attendance.static-qr.print');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:'.self::MAX_KILOBYTES, 'dimensions:min_width=300,min_height=180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'file' => ':attribute harus berupa berkas.',
            'uploaded' => ':attribute gagal diunggah. Ukuran berkas mungkin melebihi batas server.',
            'mimes' => ':attribute harus berupa PNG atau JPG.',
            'image.max' => ':attribute maksimal 5 MB.',
            'dimensions' => ':attribute terlalu kecil. Pakai gambar minimal 300 x 180 piksel.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['image' => 'Gambar kartu'];
    }
}
