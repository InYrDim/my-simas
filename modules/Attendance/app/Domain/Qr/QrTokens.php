<?php

namespace Modules\Attendance\App\Domain\Qr;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Str;
use Modules\Platform\App\Contracts\TenantCache;

/**
 * One-time codes behind a student's attendance QR. A code lives for a
 * minute, belongs to one student of the current school, and dies when it
 * is used or when the student's page asks for the next one. Nothing is
 * stored in the database, and the code says nothing about the student.
 */
final class QrTokens
{
    /**
     * Seconds a code stays valid.
     */
    public const TTL = 60;

    public function __construct(
        private readonly TenantCache $cache,
    ) {}

    /**
     * A fresh code for the student; the previous one stops working.
     *
     * @return array{token: string, expiresIn: int}
     */
    public function issue(int $studentId): array
    {
        return $this->exclusively("issue:{$studentId}", function () use ($studentId): array {
            $previous = $this->cache->get($this->studentKey($studentId));

            if (is_string($previous)) {
                $this->cache->forget($this->tokenKey($previous));
            }

            $token = Str::random(40);
            $hash = hash('sha256', $token);

            $this->cache->put($this->tokenKey($hash), $studentId, self::TTL);
            $this->cache->put($this->studentKey($studentId), $hash, self::TTL);

            return ['token' => $token, 'expiresIn' => self::TTL];
        });
    }

    /**
     * The student a code belongs to, using the code up. Null for a code
     * that is unknown, expired, already used, replaced by a newer one, or
     * issued by another school. Two scans of one code at the same moment
     * take turns, so only the first one gets the student.
     */
    public function consume(string $token): ?int
    {
        $hash = hash('sha256', $token);

        try {
            return $this->consumeUnderLock($hash);
        } catch (LockTimeoutException) {
            // Refuse rather than use the code without the lock.
            return null;
        }
    }

    /**
     * @throws LockTimeoutException
     */
    private function consumeUnderLock(string $hash): ?int
    {
        return $this->exclusively("consume:{$hash}", function () use ($hash): ?int {
            $studentId = $this->cache->get($this->tokenKey($hash));

            if (! is_int($studentId) && ! (is_string($studentId) && ctype_digit($studentId))) {
                return null;
            }

            $studentId = (int) $studentId;
            $this->cache->forget($this->tokenKey($hash));

            if ($this->cache->get($this->studentKey($studentId)) !== $hash) {
                return null;
            }

            $this->cache->forget($this->studentKey($studentId));

            return $studentId;
        });
    }

    /**
     * Run the callback while holding a lock.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     *
     * @throws LockTimeoutException
     */
    private function exclusively(string $name, Closure $callback): mixed
    {
        return $this->cache->lock("attendance:qr:{$name}", 5)->block(2, $callback);
    }

    private function tokenKey(string $hash): string
    {
        return "attendance:qr:token:{$hash}";
    }

    private function studentKey(int $studentId): string
    {
        return "attendance:qr:student:{$studentId}";
    }
}
