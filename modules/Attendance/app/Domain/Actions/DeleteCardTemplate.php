<?php

namespace Modules\Attendance\App\Domain\Actions;

use Modules\Attendance\App\Domain\Models\StaticQrCardTemplate;
use Modules\Attendance\App\Domain\Qr\CardTemplateFiles;

final class DeleteCardTemplate
{
    public function __construct(
        private readonly CardTemplateFiles $files,
    ) {}

    public function handle(StaticQrCardTemplate $template): void
    {
        $path = $template->image_path;

        $template->delete();
        $this->files->delete($path);
    }
}
