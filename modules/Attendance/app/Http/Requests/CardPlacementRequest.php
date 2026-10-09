<?php

namespace Modules\Attendance\App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Gate;
use Modules\Attendance\App\Domain\Models\StaticQrCardTemplate;

/**
 * Where the QR box sits on the card: the whole box must stay on the
 * picture.
 */
final class CardPlacementRequest extends AttendanceFormRequest
{
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
            'qr_x' => ['required', 'numeric', 'min:0', 'max:100'],
            'qr_y' => ['required', 'numeric', 'min:0', 'max:100'],
            'qr_size' => ['required', 'numeric', 'min:'.StaticQrCardTemplate::MIN_SIZE, 'max:100'],
            'card_width_mm' => ['required', 'numeric', 'between:40,300'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $template = StaticQrCardTemplate::current();

            if ($template === null || $validator->errors()->isNotEmpty()) {
                return;
            }

            $boxHeight = $this->float('qr_size') * $template->aspect();

            if ($this->float('qr_x') + $this->float('qr_size') > 100.0001 || $this->float('qr_y') + $boxHeight > 100.0001) {
                $validator->errors()->add('qr_x', 'Kotak QR harus berada di dalam gambar kartu.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'numeric' => ':attribute harus berupa angka.',
            'min' => ':attribute terlalu kecil.',
            'between' => ':attribute harus antara :min dan :max.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'qr_x' => 'Posisi QR',
            'qr_y' => 'Posisi QR',
            'qr_size' => 'Ukuran QR',
            'card_width_mm' => 'Lebar kartu (mm)',
        ];
    }
}
