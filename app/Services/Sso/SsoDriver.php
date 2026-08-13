<?php

namespace App\Services\Sso;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Every staff member authenticates as themselves via SSO.
 *
 * Implementations only have to answer two questions: where do I send the
 * browser, and which email came back. Identity matching, roster checks and
 * session creation all stay in SsoController so they can't drift between drivers.
 */
interface SsoDriver
{
    /** Start authentication. */
    public function redirect(): RedirectResponse;

    /**
     * The authenticated person's email address, or null if the callback was
     * invalid, cancelled or tampered with.
     */
    public function emailFromCallback(Request $request): ?string;

    /** Whether this driver can be used in the current environment. */
    public function isAvailable(): bool;

    /** Shown on the sign-in button. */
    public function label(): string;
}
