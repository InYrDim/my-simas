<?php

namespace Modules\Attendance\App\Domain\Qr;

use Illuminate\Support\Facades\Storage;
use Modules\Attendance\App\Domain\Models\StaticQrCardTemplate;
use Modules\Platform\App\Contracts\TenantStorage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The student-card picture, kept in the school's own private storage
 * partition (`TenantStorage`, module `attendance`) under a name the server
 * chose. A path is only ever read from the template row, never from a
 * request.
 */
final class CardTemplateFiles
{
    public const MODULE = 'attendance';

    public function __construct(
        private readonly TenantStorage $storage,
    ) {}

    public function put(string $relative, string $contents): void
    {
        $this->storage->put(self::MODULE, $relative, $contents);
    }

    public function delete(string $relative): void
    {
        $this->storage->delete(self::MODULE, $relative);
    }

    public function exists(string $relative): bool
    {
        return $this->storage->exists(self::MODULE, $relative);
    }

    /**
     * The picture as an inline image for the school's own pages, or null
     * when the file is gone from the disk.
     */
    public function response(StaticQrCardTemplate $template): ?Response
    {
        if (! $this->exists($template->image_path)) {
            return null;
        }

        return Storage::disk('local')->response(
            $this->storage->path(self::MODULE, $template->image_path),
            $template->image_name,
            [
                'Content-Type' => $template->image_mime,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=300',
            ],
        );
    }
}
