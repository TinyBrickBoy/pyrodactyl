<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Auth;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| Endpoint: /auth
|
*/

// These routes are defined so that we can continue to reference them programmatically.
// They all route to the same controller function which passes off to React.
Route::get('/login', [Auth\LoginController::class, 'index'])->name('auth.login');
Route::get('/password', [Auth\LoginController::class, 'index'])->name('auth.forgot-password');
Route::get('/password/reset/{token}', [Auth\LoginController::class, 'index'])->name('auth.reset');

// OpenID Connect single sign-on. These routes drop the "guest" middleware so an
// already authenticated user can perform an SSO re-authentication when confirming
// a destructive action (such as deleting a backup).
Route::get('/login/sso', [Auth\OpenIdController::class, 'redirect'])
  ->withoutMiddleware('guest')
  ->name('auth.sso');
Route::get('/login/sso/callback', [Auth\OpenIdController::class, 'callback'])
  ->withoutMiddleware('guest')
  ->name('auth.sso.callback');

// Apply a throttle to authentication action endpoints to slow down manual attack spammers. 🤷‍
//
// @see \Pterodactyl\Providers\RouteServiceProvider
Route::middleware(['throttle:authentication'])->group(function () {
  // Login endpoints.
  Route::post('/login', [Auth\LoginController::class, 'login'])
    ->middleware('captcha');
  Route::post('/login/checkpoint', Auth\LoginCheckpointController::class)->name('auth.login-checkpoint');

  // Forgot password route. A post to this endpoint will trigger an
  // email to be sent containing a reset token.
  Route::post('/password', [Auth\ForgotPasswordController::class, 'sendResetLinkEmail'])
    ->middleware('captcha')
    ->name('auth.post.forgot-password');
});

// Password reset routes. This endpoint is hit after going through
// the forgot password routes to acquire a token (or after an account
// is created).
Route::post('/password/reset', Auth\ResetPasswordController::class)
  ->middleware('captcha')
  ->name('auth.reset-password');

// Remove the guest middleware and apply the authenticated middleware to this endpoint,
// so it cannot be used unless you're already logged in.
Route::post('/logout', [Auth\LoginController::class, 'logout'])
  ->withoutMiddleware('guest')
  ->middleware('auth')
  ->name('auth.logout');

// Catch any other combinations of routes and pass them off to the React component.
Route::fallback([Auth\LoginController::class, 'index']);
