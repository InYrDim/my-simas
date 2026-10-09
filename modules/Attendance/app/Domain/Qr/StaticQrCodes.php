<?php

namespace Modules\Attendance\App\Domain\Qr;

use Modules\Platform\App\Contracts\TenantContext;

/**
 * The printed, never-changing QR of a student. The code carries the
 * student's id and a signature made with the application key and the
 * school, so it cannot be guessed or reused at another school. It proves
 * nothing about who holds the paper: a school only accepts it while it has
 * the static QR switched on.
 */
final class StaticQrCodes
{
    private const PREFIX = 'simas-s1';

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function payloadFor(int $studentId): string
    {
        return implode('.', [self::PREFIX, $studentId, $this->signature($studentId)]);
    }

    /**
     * Whether a scanned text is shaped like a static code (valid or not),
     * as opposed to a one-time code.
     */
    public function looksStatic(string $payload): bool
    {
        return str_starts_with($payload, self::PREFIX.'.');
    }

    /**
     * The student a static code belongs to in this school, or null for a
     * forged, damaged or foreign code.
     */
    public function studentIdFrom(string $payload): ?int
    {
        $parts = explode('.', $payload);

        if (count($parts) !== 3 || $parts[0] !== self::PREFIX || ! ctype_digit($parts[1])) {
            return null;
        }

        $studentId = (int) $parts[1];

        return hash_equals($this->signature($studentId), $parts[2]) ? $studentId : null;
    }

    private function signature(int $studentId): string
    {
        $tenantId = $this->context->currentOrFail()->id;

        return substr(hash_hmac('sha256', "static-qr:{$tenantId}:{$studentId}", (string) config('app.key')), 0, 32);
    }
}
