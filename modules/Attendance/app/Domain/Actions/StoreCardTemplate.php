<?php

namespace Modules\Attendance\App\Domain\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Attendance\App\Domain\Models\StaticQrCardTemplate;
use Modules\Attendance\App\Domain\Qr\CardTemplateFiles;

/**
 * Saves the picture of the school's student card, replacing the previous
 * one. A new picture puts the QR box back in the middle, since the old
 * place means nothing on it; the printed width is kept.
 */
final class StoreCardTemplate
{
    private const EXTENSIONS = ['image/png' => 'png', 'image/jpeg' => 'jpg'];

    private const DEFAULT_SIZE = 30.0;

    public function __construct(
        private readonly CardTemplateFiles $files,
    ) {}

    public function handle(UploadedFile $image): StaticQrCardTemplate
    {
        $info = getimagesize((string) $image->getRealPath());

        abort_if($info === false || ! isset(self::EXTENSIONS[$info['mime']]), 422, 'Gambar kartu tidak dapat dibaca.');

        [$width, $height] = $info;
        $mime = $info['mime'];
        $path = sprintf('static-qr/card-%s.%s', Str::lower(Str::random(20)), self::EXTENSIONS[$mime]);

        $this->files->put($path, (string) $image->get());

        $boxHeight = self::DEFAULT_SIZE * $width / $height;

        $attributes = [
            'image_path' => $path,
            'image_name' => $this->safeName($image->getClientOriginalName()),
            'image_mime' => $mime,
            'image_width' => $width,
            'image_height' => $height,
            'qr_x' => round((100 - self::DEFAULT_SIZE) / 2, 3),
            'qr_y' => round(max(0, (100 - $boxHeight) / 2), 3),
            'qr_size' => self::DEFAULT_SIZE,
        ];

        $template = StaticQrCardTemplate::current();
        $previousPath = $template?->image_path;

        DB::transaction(function () use (&$template, $attributes): void {
            if ($template === null) {
                $template = StaticQrCardTemplate::query()->create($attributes);

                return;
            }

            $template->fill($attributes)->save();
        });

        if ($previousPath !== null) {
            $this->files->delete($previousPath);
        }

        return $template;
    }

    /**
     * What the sender called the file, made safe to show.
     */
    private function safeName(string $name): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F\\\\\/]+/u', '', basename(str_replace('\\', '/', $name))));

        return $name === '' ? 'kartu' : mb_substr($name, 0, 120);
    }
}
