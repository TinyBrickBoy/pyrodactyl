<?php

namespace Pterodactyl\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\Crypt;
use Pterodactyl\Exceptions\Http\SsoReauthenticationRequiredException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a single place to re-confirm a user's identity before a destructive
 * action. Password-based sessions confirm with their password (and TOTP when
 * enabled); sessions that authenticated through SSO confirm with a fresh SSO
 * re-authentication instead, since those accounts have no usable password.
 */
trait ConfirmsSensitiveActions
{
    /**
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     * @throws \Pterodactyl\Exceptions\Http\SsoReauthenticationRequiredException
     */
    protected function confirmSensitiveAction(Request $request, Google2FA $google2FA): void
    {
        $user = $request->user();

        // API key requests are already authenticated by their token and are not
        // subject to the interactive confirmation requirement.
        if ($user->currentAccessToken()) {
            return;
        }

        // Users who signed in through SSO have no usable password — require a
        // recent SSO re-authentication instead.
        if ($request->session()->get('auth_via_sso')) {
            $confirmedAt = (int) $request->session()->get('sso_confirmed_at', 0);
            $window = (int) config('openid.reauth_window', 300);

            if ($confirmedAt <= 0 || (time() - $confirmedAt) > $window) {
                throw new SsoReauthenticationRequiredException();
            }

            return;
        }

        $password = $request->input('password');
        if (empty($password) || !password_verify($password, $user->password)) {
            throw new BadRequestHttpException('The password provided was not valid.');
        }

        if ($user->use_totp) {
            $totpCode = $request->input('totp_code');
            if (empty($totpCode)) {
                throw new BadRequestHttpException('Two-factor authentication code is required.');
            }

            $secret = Crypt::decrypt($user->totp_secret);
            if (!$google2FA->verifyKey($secret, $totpCode)) {
                throw new BadRequestHttpException('The two-factor authentication code provided was not valid.');
            }
        }
    }
}
