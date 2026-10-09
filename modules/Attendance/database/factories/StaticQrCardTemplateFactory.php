<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\App\Domain\Models\StaticQrCardTemplate;

/**
 * A landscape card picture with the QR box in the middle. The file at
 * `image_path` is not created.
 *
 * @extends Factory<StaticQrCardTemplate>
 */
class StaticQrCardTemplateFactory extends Factory
{
    protected $model = StaticQrCardTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'image_path' => 'static-qr/card-'.fake()->lexify('????????').'.png',
            'image_name' => 'kartu.png',
            'image_mime' => 'image/png',
            'image_width' => 856,
            'image_height' => 540,
            'qr_x' => 35,
            'qr_y' => 25,
            'qr_size' => 30,
            'card_width_mm' => 85.6,
        ];
    }
}
