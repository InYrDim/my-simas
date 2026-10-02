<?php

namespace Modules\Platform\App\Contracts;

use Modules\Platform\App\Contracts\DTOs\WhatsappSendResult;
use Modules\Platform\App\Contracts\DTOs\WhatsappState;
use Modules\Platform\App\Contracts\Exceptions\TenantNotSetException;
use Modules\Platform\App\Contracts\Exceptions\WhatsappUnavailableException;

/**
 * The WhatsApp number of the school in the current tenant context. A
 * school asks for it, the provider approves (or the provider's
 * "approve automatically" setting does), then the school links a number.
 *
 * Every method works on the ambient tenant and fails closed without one.
 * The gateway credentials stay inside Platform: nothing returned here
 * carries a key. A gateway that refuses or cannot be reached does not
 * throw: the state comes back with `lastError` filled in.
 */
interface WhatsappChannel
{
    /**
     * @param  bool  $refresh  ask the gateway where the session stands (and for the QR while there is one) instead of answering from what was last seen
     *
     * @throws TenantNotSetException
     */
    public function state(bool $refresh = false): WhatsappState;

    /**
     * Ask the provider for WhatsApp. Asking again while a request is
     * waiting, approved or disabled changes nothing; a rejected request is
     * sent back to review. With automatic approval on, a new request is
     * approved on the spot when the gateway cooperates, and waits for the
     * provider when it does not.
     *
     * @param  int  $requestedBy  school user id
     *
     * @throws TenantNotSetException
     */
    public function request(int $requestedBy): WhatsappState;

    /**
     * Start linking a number: the session's engine boots and, moments
     * later, `state(refresh: true)` carries the QR to scan. Pressing it
     * again while it is running, or already linked, changes nothing.
     *
     * @throws TenantNotSetException
     * @throws WhatsappUnavailableException not approved, or disabled
     */
    public function connect(): WhatsappState;

    /**
     * Unlink the number. Linking again needs a new QR.
     *
     * @throws TenantNotSetException
     * @throws WhatsappUnavailableException not approved, or disabled
     */
    public function disconnect(): WhatsappState;

    /**
     * Send one text message from the school's number. Never throws for
     * the gateway or for a school without linked WhatsApp: the result
     * says what happened.
     *
     * @param  string  $phone  digits only, international format without `+` (`62812…`)
     *
     * @throws TenantNotSetException
     */
    public function sendText(string $phone, string $text): WhatsappSendResult;
}
