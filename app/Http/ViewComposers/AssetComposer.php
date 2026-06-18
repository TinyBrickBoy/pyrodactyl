<?php

namespace Pterodactyl\Http\ViewComposers;

use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Services\Auth\OpenIdService;
use Pterodactyl\Services\Helpers\AssetHashService;
use Pterodactyl\Services\Captcha\CaptchaManager;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class AssetComposer
{
  protected CaptchaManager $captcha;
  protected SettingsRepositoryInterface $settings;
  protected OpenIdService $openid;

  public function __construct(CaptchaManager $captcha, SettingsRepositoryInterface $settings, OpenIdService $openid)
  {
    $this->captcha = $captcha;
    $this->settings = $settings;
    $this->openid = $openid;
  }

  /**
   * Provide access to the asset service in the views.
   */
  public function compose(View $view): void
  {
    $view->with('siteConfiguration', [
      'name' => config('app.name') ?? 'Pyrodactyl',
      'locale' => config('app.locale') ?? 'en',
      'timezone' => config('app.timezone') ?? '',
      'captcha' => [
        'enabled' => $this->captcha->getDefaultDriver() !== 'none',
        'provider' => $this->captcha->getDefaultDriver(),
        'siteKey' => $this->getSiteKeyForCurrentProvider(),
        'scriptIncludes' => $this->captcha->getScriptIncludes(),
      ],
      'sso' => [
        // Whether the "Sign in with ..." button should be shown on the login page.
        'enabled' => $this->openid->enabled(),
        'displayName' => config('openid.display_name'),
        // True when the current session authenticated through SSO; the client uses
        // this to swap password confirmation prompts for an SSO re-authentication.
        'authenticated' => (bool) session('auth_via_sso', false),
      ],
    ]);
  }

  /**
   * Get the site key for the currently active captcha provider.
   */
  private function getSiteKeyForCurrentProvider(): string
  {
    $provider = $this->captcha->getDefaultDriver();

    if ($provider === 'none') {
      return '';
    }

    try {
      $driver = $this->captcha->driver();
      if (method_exists($driver, 'getSiteKey')) {
        return $driver->getSiteKey();
      }
    } catch (\Exception $e) {
      // Silently fail to avoid exposing errors to frontend
    }

    return '';
  }
}
