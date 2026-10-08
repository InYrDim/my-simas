<?php

namespace Modules\Platform\App\Infrastructure\Whatsapp;

use Illuminate\Support\Carbon;
use Modules\Platform\App\Contracts\DTOs\WhatsappSendResult;
use Modules\Platform\App\Contracts\DTOs\WhatsappState;
use Modules\Platform\App\Contracts\Exceptions\WhatsappRiskNotAcknowledgedException;
use Modules\Platform\App\Contracts\Exceptions\WhatsappUnavailableException;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\WhatsappChannel;
use Modules\Platform\App\Domain\Models\TenantStatus;
use Modules\Platform\App\Domain\Models\WhatsappInstance;
use Modules\Platform\App\Domain\Models\WhatsappInstanceStatus;

/**
 * Default WhatsappChannel: the ambient tenant's row in whatsapp_instances.
 * The row is always looked up by the explicit tenant id (the model is not
 * tenant-scoped), and only WhatsappState leaves this class.
 *
 * A gateway that refuses or cannot be reached never throws out of here:
 * the trouble is kept on the row in words a school may read and comes
 * back as `lastError`.
 */
final class DefaultWhatsappChannel implements WhatsappChannel
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly RequestWhatsappInstance $request,
        private readonly OpenWaClient $gateway,
        private readonly SyncWhatsappSession $sync,
        private readonly SendGuard $guard,
    ) {}

    public function state(bool $refresh = false): WhatsappState
    {
        $instance = $this->instance();
        $qrCode = $refresh && $instance?->usable() === true ? $this->sync->handle($instance) : null;

        return $this->describe($instance, $qrCode);
    }

    public function request(int $requestedBy): WhatsappState
    {
        return $this->describe(
            $this->request->handle($this->context->currentOrFail()->id, $requestedBy),
        );
    }

    public function connect(): WhatsappState
    {
        $instance = $this->usableInstance();

        if ($instance->risk_acknowledged_at === null) {
            throw new WhatsappRiskNotAcknowledgedException;
        }

        $sessionId = (string) $instance->session_id;

        try {
            $key = (string) $instance->api_key;
            $this->sync->record($instance, $this->gateway->session($sessionId, $key));

            if (! in_array($instance->connection_status, SyncWhatsappSession::RUNNING, true)) {
                $instance->forceFill([
                    'connection_status' => $this->start($sessionId, $key),
                    'last_error' => null,
                ])->save();
            }
        } catch (OpenWaException $exception) {
            $instance->forceFill(['last_error' => $exception->forSchool()])->save();
        }

        return $this->describe($instance);
    }

    public function acknowledgeRisk(int $userId): WhatsappState
    {
        $instance = $this->usableInstance();

        // The first acceptance wins: the guarded update leaves a recorded one alone.
        WhatsappInstance::query()
            ->whereKey($instance->getKey())
            ->whereNull('risk_acknowledged_at')
            ->update(['risk_acknowledged_by' => $userId, 'risk_acknowledged_at' => now()]);

        return $this->describe($instance->refresh());
    }

    public function disconnect(): WhatsappState
    {
        $instance = $this->usableInstance();
        $sessionId = (string) $instance->session_id;

        try {
            $key = (string) $instance->api_key;

            // Unlink the number, then stop the engine so it does not sit
            // waiting with a fresh QR. A session that was never started
            // answers 400 to the logout: there is nothing to unlink.
            $this->ignoring400(fn () => $this->gateway->logoutSession($sessionId, $key));
            $this->ignoring400(fn () => $this->gateway->stopSession($sessionId, $key));

            $instance->forceFill([
                'connection_status' => 'disconnected',
                'phone' => null,
                'push_name' => null,
                'connected_at' => null,
                'last_error' => null,
            ])->save();
        } catch (OpenWaException $exception) {
            $instance->forceFill(['last_error' => $exception->forSchool()])->save();
        }

        return $this->describe($instance);
    }

    public function sendText(string $phone, string $text): WhatsappSendResult
    {
        // A suspended school sends nothing: messages queued before it was
        // closed are closed as not sent and are not offered again.
        if ($this->context->currentOrFail()->status === TenantStatus::Suspended) {
            return WhatsappSendResult::suspended();
        }

        $instance = $this->instance();

        // Decided from what was last seen: a school that is not linked
        // costs no call to the gateway.
        if ($instance === null || ! $instance->usable() || $instance->connection_status !== 'ready') {
            return WhatsappSendResult::unavailable();
        }

        if (preg_match('/^\d{8,15}$/', $phone) !== 1) {
            return WhatsappSendResult::failed('Nomor tujuan tidak sah.', false);
        }

        $held = $this->guard->hold();

        if ($held !== null) {
            return $held;
        }

        try {
            $sent = $this->gateway->sendText((string) $instance->session_id, (string) $instance->api_key, "{$phone}@c.us", $text);
        } catch (OpenWaException $exception) {
            if ($exception->retryable()) {
                $this->guard->recordFailure();
            }

            return WhatsappSendResult::failed($exception->forSchool(), $exception->retryable());
        }

        $this->guard->recordSent();

        return WhatsappSendResult::sent($sent['messageId']);
    }

    /**
     * Boot the session's engine and say where it stands. The gateway
     * answers 400 when it is already running, which is what was wanted.
     */
    private function start(string $sessionId, #[\SensitiveParameter] string $key): string
    {
        try {
            $status = $this->gateway->startSession($sessionId, $key)['status'] ?? null;
        } catch (OpenWaException $exception) {
            if ($exception->status !== 400) {
                throw $exception;
            }

            $status = null;
        }

        return in_array($status, SyncWhatsappSession::RUNNING, true) ? $status : 'initializing';
    }

    /**
     * @param  callable(): mixed  $call
     */
    private function ignoring400(callable $call): void
    {
        try {
            $call();
        } catch (OpenWaException $exception) {
            if ($exception->status !== 400) {
                throw $exception;
            }
        }
    }

    private function instance(): ?WhatsappInstance
    {
        return WhatsappInstance::query()
            ->where('tenant_id', $this->context->currentOrFail()->id)
            ->first();
    }

    /**
     * @throws WhatsappUnavailableException
     */
    private function usableInstance(): WhatsappInstance
    {
        $instance = $this->instance();

        if ($instance === null || ! $instance->usable()) {
            throw new WhatsappUnavailableException;
        }

        return $instance;
    }

    private function describe(?WhatsappInstance $instance, ?string $qrCode = null): WhatsappState
    {
        if ($instance === null) {
            return new WhatsappState(WhatsappState::STAGE_NONE);
        }

        $active = $instance->status === WhatsappInstanceStatus::Active;
        $pause = $active ? $this->guard->pause() : null;
        $decidedAgainst = in_array($instance->status, [WhatsappInstanceStatus::Rejected, WhatsappInstanceStatus::Disabled], true);

        return new WhatsappState(
            stage: $instance->status->value,
            connection: $active ? $instance->connection_status : null,
            phone: $active ? $instance->phone : null,
            pushName: $active ? $instance->push_name : null,
            note: $decidedAgainst ? $instance->note : null,
            qrCode: $active ? $qrCode : null,
            // Only what the session sync wrote: a failed approval or a
            // failed stop is the provider's to read, not the school's.
            lastError: $active ? $instance->last_error : null,
            requestedAt: $instance->requested_at?->toIso8601String(),
            pausedUntil: $pause === null ? null : Carbon::createFromTimestamp($pause['until'])->toIso8601String(),
            pauseReason: $pause['reason'] ?? null,
            riskAcknowledged: $instance->risk_acknowledged_at !== null,
        );
    }
}
