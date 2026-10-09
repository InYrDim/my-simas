<?php

namespace Modules\Attendance\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Attendance\Database\Factories\StaticQrCardTemplateFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * The school's student-card picture with the place of the static QR on it
 * (one row per school). `qr_x` and `qr_y` are the box's left and top edge
 * as a percentage of the picture's width and height, `qr_size` its side as
 * a percentage of the width, so the box is always square. `card_width_mm`
 * is the printed width of the card; its height follows the picture.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $image_path
 * @property string $image_name
 * @property string $image_mime
 * @property int $image_width
 * @property int $image_height
 * @property float $qr_x
 * @property float $qr_y
 * @property float $qr_size
 * @property float $card_width_mm
 */
#[UseFactory(StaticQrCardTemplateFactory::class)]
#[Fillable(['image_path', 'image_name', 'image_mime', 'image_width', 'image_height', 'qr_x', 'qr_y', 'qr_size', 'card_width_mm'])]
class StaticQrCardTemplate extends Model
{
    /** @use HasFactory<StaticQrCardTemplateFactory> */
    use BelongsToTenant, HasFactory;

    public const MIN_SIZE = 5.0;

    /**
     * The current school's template, if it has uploaded one.
     */
    public static function current(): ?self
    {
        return self::query()->first();
    }

    /**
     * Width over height of the picture.
     */
    public function aspect(): float
    {
        return $this->image_width / max(1, $this->image_height);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qr_x' => 'float',
            'qr_y' => 'float',
            'qr_size' => 'float',
            'card_width_mm' => 'float',
            'image_width' => 'integer',
            'image_height' => 'integer',
        ];
    }
}
