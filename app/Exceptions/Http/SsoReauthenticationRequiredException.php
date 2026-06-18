<?php

namespace Pterodactyl\Exceptions\Http;

use Pterodactyl\Exceptions\DisplayException;

/**
 * Thrown when a user who signed in through SSO attempts a destructive action
 * that normally requires password confirmation. Because SSO users have no
 * usable account password, they must instead re-authenticate with the identity
 * provider. The frontend detects this exception's code ("Sso...Exception") and
 * starts the SSO re-authentication flow.
 */
class SsoReauthenticationRequiredException extends DisplayException
{
    public function __construct()
    {
        parent::__construct('Please re-authenticate with your SSO provider to confirm this action.');
    }
}
