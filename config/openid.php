<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenID Connect Single Sign-On
    |--------------------------------------------------------------------------
    |
    | Configuration for a generic OpenID Connect (OIDC) identity provider. This
    | works with any spec-compliant provider that exposes a discovery document
    | at "{issuer}/.well-known/openid-configuration" — e.g. Keycloak, Authentik,
    | Authelia, Microsoft Entra ID (Azure AD), Google, Okta, and others.
    |
    | Password based logins continue to work alongside SSO; this simply adds an
    | additional "Sign in with ..." option on the login screen.
    |
    */

    // Master switch for the SSO integration.
    'enabled' => env('OPENID_ENABLED', false),

    // Label shown on the login button, e.g. "Sign in with Authentik".
    'display_name' => env('OPENID_DISPLAY_NAME', 'Single Sign-On'),

    // The issuer / base URL of the identity provider used for discovery.
    'issuer' => env('OPENID_ISSUER'),

    // OAuth2 client credentials registered with the provider.
    'client_id' => env('OPENID_CLIENT_ID'),
    'client_secret' => env('OPENID_CLIENT_SECRET'),

    // Scopes requested during the authorization request.
    'scopes' => env('OPENID_SCOPES', 'openid profile email'),

    // When true, users authenticating through SSO for the first time will be
    // provisioned automatically. When false, an account with a matching email
    // address must already exist for the login to succeed.
    'auto_create' => env('OPENID_AUTO_CREATE', true),

    // When enabled new SSO users are granted administrator access. Leave this
    // disabled unless your provider is the sole source of trusted admins.
    'create_as_admin' => env('OPENID_CREATE_AS_ADMIN', false),

    // How long (in seconds) the OIDC discovery document and JWKS are cached.
    'cache_ttl' => env('OPENID_CACHE_TTL', 3600),

    // How long (in seconds) a fresh SSO re-authentication remains valid when
    // confirming destructive actions (such as deleting a backup) for users who
    // signed in through SSO and therefore have no usable account password.
    'reauth_window' => env('OPENID_REAUTH_WINDOW', 300),
];
