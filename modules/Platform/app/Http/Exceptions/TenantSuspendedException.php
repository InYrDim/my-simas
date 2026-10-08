<?php

namespace Modules\Platform\App\Http\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Thrown by ResolveTenant for a suspended school. It renders itself as one
 * neutral page for every reason a school can be suspended: the school code
 * is public, so the page names the school and the provider's contact and
 * nothing else (no amounts, invoices, plan or reason).
 *
 * Still a 403, as before, so everything that already expects one keeps
 * working. The page is plain HTML with inline CSS: it must show even when
 * the assets do not load, and it is thrown before the Inertia stack is
 * guaranteed to be ready.
 */
final class TenantSuspendedException extends AccessDeniedHttpException
{
    public const MESSAGE = 'Akses sekolah ini sedang dihentikan sementara. Hubungi pengelola sekolah Anda atau penyedia layanan.';

    public function __construct(public readonly string $schoolName)
    {
        parent::__construct(self::MESSAGE);
    }

    public function render(Request $request): Response|JsonResponse
    {
        $headers = [
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex',
        ];

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => self::MESSAGE], 403, $headers);
        }

        $issuer = (array) config('billing.issuer', []);
        $whatsapp = preg_replace('/\D+/', '', (string) ($issuer['whatsapp'] ?? ''));

        return response()->view('Platform::tenant-suspended', [
            'schoolName' => $this->schoolName,
            'message' => self::MESSAGE,
            'providerName' => (string) ($issuer['name'] ?? ''),
            'providerEmail' => $issuer['email'] ?? null,
            'providerPhone' => $issuer['phone'] ?? null,
            'whatsappUrl' => $whatsapp === '' ? null : "https://wa.me/{$whatsapp}",
        ], 403, $headers);
    }
}
