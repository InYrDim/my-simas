<?php

namespace Modules\Platform\App\Infrastructure\Whatsapp;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Modules\Platform\App\Contracts\DTOs\WhatsappSendResult;
use Modules\Platform\App\Contracts\TenantCache;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Keeps one school's number from looking like a bot: a circuit breaker
 * that pauses sending after consecutive gateway failures, a daily limit,
 * and a random pause between two messages. All state lives in the
 * tenant-partitioned cache, so schools never affect each other.
 *
 * Only plain integers are cached (never models, never null). The day of
 * the daily limit follows the school's timezone from TenantContext, which
 * falls back to the configured default and then UTC.
 */
final class SendGuard
{
    private const STATE_TTL = 172800;

    private const FAILURES = 'whatsapp:failures';

    private const BREAKER_UNTIL = 'whatsapp:breaker-until';

    private const NEXT_SLOT = 'whatsapp:next-slot';

    public function __construct(
        private readonly TenantCache $cache,
        private readonly TenantContext $context,
    ) {}

    /**
     * Decide whether the next message may go now. A pacing slot is
     * reserved when it may; the daily count only moves on `recordSent()`.
     * Returns null to go ahead, or the `throttled` result to hand back.
     */
    public function hold(): ?WhatsappSendResult
    {
        $pause = $this->pause();

        if ($pause !== null) {
            return WhatsappSendResult::throttled($pause['until'] - now()->getTimestamp(), $pause['reason']);
        }

        try {
            return $this->cache->lock('whatsapp-pacing', 5)->block(3, fn (): ?WhatsappSendResult => $this->reserveSlot());
        } catch (LockTimeoutException) {
            return WhatsappSendResult::throttled(1, 'Menunggu giliran kirim.');
        }
    }

    /**
     * Why and until when sending is held back (open breaker or daily
     * limit reached), or null when it is not.
     *
     * @return array{until: int, reason: string}|null
     */
    public function pause(): ?array
    {
        $now = now()->getTimestamp();
        $until = (int) $this->cache->get(self::BREAKER_UNTIL, 0);

        if ($until > $now) {
            return ['until' => $until, 'reason' => 'Pengiriman dijeda sementara karena gateway WhatsApp gagal berulang kali.'];
        }

        $limit = (int) config('services.openwa.daily_limit');

        if ($limit > 0 && $this->sentToday() >= $limit) {
            return ['until' => $this->midnight(), 'reason' => 'Batas kirim harian tercapai, pengiriman dilanjutkan besok.'];
        }

        return null;
    }

    /**
     * A message was accepted by the gateway: it counts for today and
     * clears the run of failures.
     */
    public function recordSent(): void
    {
        $this->exclusively(function (): void {
            $this->cache->put($this->dailyKey(), $this->sentToday() + 1, self::STATE_TTL);
            $this->cache->forget(self::FAILURES);
        });
    }

    /**
     * The gateway failed in a way that may pass (unreachable, 5xx). The
     * breaker opens at the threshold. The count is kept when it opens, so
     * a single failure after the pause opens it again; a success resets it.
     */
    public function recordFailure(): void
    {
        $this->exclusively(function (): void {
            $failures = (int) $this->cache->get(self::FAILURES, 0) + 1;
            $this->cache->put(self::FAILURES, $failures, self::STATE_TTL);

            if ($failures >= max(1, (int) config('services.openwa.breaker_threshold'))) {
                $pause = max(1, (int) config('services.openwa.breaker_pause'));
                $this->cache->put(self::BREAKER_UNTIL, now()->getTimestamp() + $pause, $pause);
            }
        });
    }

    private function reserveSlot(): ?WhatsappSendResult
    {
        $now = now()->getTimestamp();
        $next = (int) $this->cache->get(self::NEXT_SLOT, 0);

        if ($next > $now) {
            return WhatsappSendResult::throttled($next - $now, 'Menunggu jeda antar pesan.');
        }

        $min = max(0, (int) config('services.openwa.pace_min'));
        $max = max($min, (int) config('services.openwa.pace_max'));

        $this->cache->put(self::NEXT_SLOT, $now + random_int($min, $max), self::STATE_TTL);

        return null;
    }

    private function sentToday(): int
    {
        return (int) $this->cache->get($this->dailyKey(), 0);
    }

    private function dailyKey(): string
    {
        return 'whatsapp:sent:'.now($this->context->timezone())->format('Y-m-d');
    }

    private function midnight(): int
    {
        return now($this->context->timezone())->addDay()->startOfDay()->getTimestamp();
    }

    private function exclusively(callable $callback): void
    {
        try {
            $this->cache->lock('whatsapp-guard', 5)->block(3, $callback);
        } catch (LockTimeoutException) {
            // Bookkeeping only: losing one tick is better than failing a send that went out.
        }
    }
}
