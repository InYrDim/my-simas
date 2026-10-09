<?php

namespace Modules\Attendance\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Domain\Actions\DeleteCardTemplate;
use Modules\Attendance\App\Domain\Actions\SaveCardPlacement;
use Modules\Attendance\App\Domain\Actions\StoreCardTemplate;
use Modules\Attendance\App\Domain\Models\StaticQrCardTemplate;
use Modules\Attendance\App\Domain\Qr\CardTemplateFiles;
use Modules\Attendance\App\Http\Requests\CardImageRequest;
use Modules\Attendance\App\Http\Requests\CardPlacementRequest;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Template Kartu: the school's student-card picture and the place of the
 * static QR on it. Every route asks `attendance.static-qr.print`. The
 * picture is served only from here, to the school's own signed-in staff.
 */
final class StaticQrCardController
{
    public function show(): Response
    {
        return Inertia::render('Attendance/StaticQrCard', [
            'template' => self::describe(StaticQrCardTemplate::current()),
            'maxMegabytes' => CardImageRequest::MAX_KILOBYTES / 1024,
            'minSize' => StaticQrCardTemplate::MIN_SIZE,
        ]);
    }

    public function image(CardTemplateFiles $files): HttpResponse
    {
        $template = StaticQrCardTemplate::current();
        $response = $template === null ? null : $files->response($template);

        abort_if($response === null, 404);

        return $response;
    }

    public function store(CardImageRequest $request, StoreCardTemplate $store): RedirectResponse
    {
        $store->handle($request->file('image'));

        return back()->with('status', 'Gambar kartu disimpan. Atur letak QR lalu simpan.');
    }

    public function update(CardPlacementRequest $request, SaveCardPlacement $save): RedirectResponse
    {
        $template = StaticQrCardTemplate::current();

        abort_if($template === null, 404);

        $save->handle(
            $template,
            $request->float('qr_x'),
            $request->float('qr_y'),
            $request->float('qr_size'),
            $request->float('card_width_mm'),
        );

        return back()->with('status', 'Letak QR disimpan.');
    }

    public function destroy(DeleteCardTemplate $delete): RedirectResponse
    {
        $template = StaticQrCardTemplate::current();

        abort_if($template === null, 404);

        $delete->handle($template);

        return back()->with('status', 'Template kartu dihapus.');
    }

    /**
     * What the pages need to draw the card: the picture's address (changes
     * with the picture, so a replaced one is not served from the browser's
     * cache) and the place of the QR.
     *
     * @return array{imageUrl: string, imageName: string, aspect: float, x: float, y: float, size: float, widthMm: float}|null
     */
    public static function describe(?StaticQrCardTemplate $template): ?array
    {
        if ($template === null) {
            return null;
        }

        return [
            'imageUrl' => route('attendance.static-qr.card.image', ['v' => $template->updated_at?->timestamp]),
            'imageName' => $template->image_name,
            'aspect' => $template->aspect(),
            'x' => $template->qr_x,
            'y' => $template->qr_y,
            'size' => $template->qr_size,
            'widthMm' => $template->card_width_mm,
        ];
    }
}
