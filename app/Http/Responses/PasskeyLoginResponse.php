<?php

namespace App\Http\Responses;

use App\Services\LoginSession;
use Laravel\Passkeys\Http\Responses\PasskeyLoginResponse as BasePasskeyLoginResponse;

/**
 * Nach dem Passkey-Login (das Paket hat schon eingeloggt und die Session erneuert)
 * die 7-Tage-Frist starten wie bei den anderen Login-Wegen.
 */
class PasskeyLoginResponse extends BasePasskeyLoginResponse
{
    public function __construct(private LoginSession $loginSession) {}

    /**
     * {@inheritDoc}
     */
    public function toResponse($request)
    {
        $this->loginSession->markAsStarted();

        return parent::toResponse($request);
    }
}
