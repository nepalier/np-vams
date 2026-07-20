<?php

declare(strict_types=1);

namespace App\Support\Auth;

use PragmaRX\Google2FA\Google2FA;

/**
 * Thin wrapper around the TOTP algorithm so the rest of the app depends on
 * this interface, not on the underlying library directly.
 */
class TotpVerifier
{
    public function __construct(private readonly Google2FA $engine = new Google2FA) {}

    public function verify(?string $secret, string $code): bool
    {
        if (empty($secret)) {
            return false;
        }

        // Allow 1 window (30s) of clock drift either side, matching common
        // authenticator app behaviour (Google Authenticator, Authy, etc.).
        return $this->engine->verifyKey($secret, $code, 1);
    }

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey();
    }

    public function qrCodeUrl(string $companyName, string $email, string $secret): string
    {
        return $this->engine->getQRCodeUrl($companyName, $email, $secret);
    }
}
