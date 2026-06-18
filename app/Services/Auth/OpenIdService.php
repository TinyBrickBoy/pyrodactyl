<?php

namespace Pterodactyl\Services\Auth;

use RuntimeException;
use Illuminate\Support\Arr;
use phpseclib3\Math\BigInteger;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Signer\Rsa\Sha384;
use Lcobucci\JWT\Signer\Rsa\Sha512;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Validator;
use phpseclib3\Crypt\PublicKeyLoader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Lcobucci\JWT\Validation\Constraint\SignedWith;

/**
 * A small, dependency-light OpenID Connect (OIDC) client. It relies only on
 * packages that already ship with the panel — Guzzle (via the HTTP facade) for
 * network calls, lcobucci/jwt for parsing/verifying ID tokens, and phpseclib3
 * for reconstructing the provider's public keys from its JWKS document.
 */
class OpenIdService
{
    /**
     * Determine whether the SSO integration has been fully configured.
     */
    public function enabled(): bool
    {
        return (bool) config('openid.enabled')
            && !empty(config('openid.issuer'))
            && !empty(config('openid.client_id'))
            && !empty(config('openid.client_secret'));
    }

    /**
     * Retrieve (and cache) the provider's OIDC discovery document.
     */
    public function discover(): array
    {
        return Cache::remember('openid.discovery', (int) config('openid.cache_ttl'), function () {
            $url = rtrim((string) config('openid.issuer'), '/') . '/.well-known/openid-configuration';

            $response = Http::acceptJson()->get($url);
            if (!$response->successful()) {
                throw new RuntimeException('Failed to load the OpenID Connect discovery document.');
            }

            return $response->json();
        });
    }

    /**
     * Build the authorization URL the user's browser should be redirected to.
     */
    public function authorizationUrl(string $state, string $nonce, bool $forceReauthentication = false): string
    {
        $discovery = $this->discover();

        $query = [
            'client_id' => config('openid.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => config('openid.scopes'),
            'state' => $state,
            'nonce' => $nonce,
        ];

        // Force the provider to re-prompt for credentials when we are confirming
        // a destructive action rather than performing an initial login.
        if ($forceReauthentication) {
            $query['prompt'] = 'login';
            $query['max_age'] = 0;
        }

        return $discovery['authorization_endpoint'] . '?' . http_build_query($query);
    }

    /**
     * Exchange an authorization code for a set of tokens.
     */
    public function exchangeCode(string $code): array
    {
        $discovery = $this->discover();

        $response = Http::asForm()->acceptJson()->post($discovery['token_endpoint'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'client_id' => config('openid.client_id'),
            'client_secret' => config('openid.client_secret'),
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to exchange the authorization code for an access token.');
        }

        return $response->json();
    }

    /**
     * Validate an ID token and return its verified claims.
     *
     * Verification checks the signature against the provider's published JWKS,
     * and asserts the issuer, audience, expiry, and nonce match what we expect.
     */
    public function validateIdToken(string $idToken, string $expectedNonce): array
    {
        /** @var UnencryptedToken $token */
        $token = (new Parser(new JoseEncoder()))->parse($idToken);

        $kid = $token->headers()->get('kid');
        $alg = $token->headers()->get('alg', 'RS256');

        $pem = $this->resolveSigningKey($kid);

        $validator = new Validator();
        $validator->assert($token, new SignedWith($this->signerFor($alg), InMemory::plainText($pem)));

        $claims = $token->claims();

        // Issuer must match the configured provider exactly.
        if ($claims->get('iss') !== $this->issuer()) {
            throw new RuntimeException('The ID token issuer did not match the configured provider.');
        }

        // Our client id must be present in the audience claim.
        $audience = Arr::wrap($claims->get('aud'));
        if (!in_array(config('openid.client_id'), $audience, true)) {
            throw new RuntimeException('The ID token audience did not include this client.');
        }

        // Reject expired tokens (with a small allowance for clock skew).
        $expiry = $claims->get('exp');
        if ($expiry instanceof \DateTimeInterface && $expiry->getTimestamp() < (time() - 30)) {
            throw new RuntimeException('The ID token has expired.');
        }

        // The nonce binds this token to the authorization request we started.
        if ($claims->get('nonce') !== $expectedNonce) {
            throw new RuntimeException('The ID token nonce did not match the login request.');
        }

        return $claims->all();
    }

    /**
     * Fetch additional profile claims from the userinfo endpoint. Used when the
     * ID token itself does not carry the email/profile claims we need.
     */
    public function userInfo(string $accessToken): array
    {
        $discovery = $this->discover();
        if (empty($discovery['userinfo_endpoint'])) {
            return [];
        }

        $response = Http::withToken($accessToken)->acceptJson()->get($discovery['userinfo_endpoint']);

        return $response->successful() ? $response->json() : [];
    }

    /**
     * The callback URL the provider will redirect back to.
     */
    public function redirectUri(): string
    {
        return route('auth.sso.callback');
    }

    /**
     * Resolve the expected issuer string from the discovery document, falling
     * back to the configured issuer.
     */
    protected function issuer(): string
    {
        $discovery = $this->discover();

        return $discovery['issuer'] ?? rtrim((string) config('openid.issuer'), '/');
    }

    /**
     * Locate the JSON Web Key matching the given key id and convert it into a
     * PEM encoded public key that lcobucci/jwt can verify against.
     */
    protected function resolveSigningKey(?string $kid): string
    {
        $discovery = $this->discover();

        $jwks = Cache::remember('openid.jwks', (int) config('openid.cache_ttl'), function () use ($discovery) {
            $response = Http::acceptJson()->get($discovery['jwks_uri']);
            if (!$response->successful()) {
                throw new RuntimeException('Failed to load the provider JWKS.');
            }

            return $response->json('keys', []);
        });

        $key = collect($jwks)->first(function (array $entry) use ($kid) {
            return $kid === null || ($entry['kid'] ?? null) === $kid;
        });

        if ($key === null || ($key['kty'] ?? null) !== 'RSA') {
            throw new RuntimeException('No matching RSA signing key was found in the provider JWKS.');
        }

        $rsa = PublicKeyLoader::load([
            'e' => new BigInteger($this->base64UrlDecode($key['e']), 256),
            'n' => new BigInteger($this->base64UrlDecode($key['n']), 256),
        ]);

        return (string) $rsa->toString('PKCS8');
    }

    /**
     * Map a JWA algorithm identifier to an lcobucci/jwt signer.
     */
    protected function signerFor(string $alg): Sha256|Sha384|Sha512
    {
        return match ($alg) {
            'RS384' => new Sha384(),
            'RS512' => new Sha512(),
            default => new Sha256(),
        };
    }

    protected function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }
}
