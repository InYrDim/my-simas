<?php

namespace Modules\Attendance\App\Domain\Actions;

use Modules\Attendance\App\Domain\Models\StaticQrCardTemplate;

final class SaveCardPlacement
{
    /**
     * @param  float  $x  percent of the picture's width, from the left edge
     * @param  float  $y  percent of the picture's height, from the top edge
     * @param  float  $size  percent of the picture's width
     * @param  float  $widthMm  the card's printed width
     */
    public function handle(StaticQrCardTemplate $template, float $x, float $y, float $size, float $widthMm): StaticQrCardTemplate
    {
        $template->fill([
            'qr_x' => $x,
            'qr_y' => $y,
            'qr_size' => $size,
            'card_width_mm' => $widthMm,
        ])->save();

        return $template;
    }
}
