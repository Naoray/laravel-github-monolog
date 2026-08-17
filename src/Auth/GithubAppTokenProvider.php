<?php

namespace Naoray\LaravelGithubMonolog\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class GithubAppTokenProvider implements TokenProviderInterface
{
    /**
     * Installation access tokens are valid for 60 minutes. We cache them for
     * less than that to avoid ever handing out a token that expires
     * mid-request.
     */
    private const CACHE_TTL = 50 * 60;

    public function __construct(
        private readonly string $clientId,
        private readonly string $installationId,
        private readonly string $privateKey,
        private readonly ?string $cacheStore = null,
        private readonly string $cachePrefix = 'github-monolog:',
    ) {}

    public function getToken(): string
    {
        return Cache::store($this->cacheStore)->remember(
            "{$this->cachePrefix}app-token:{$this->clientId}:{$this->installationId}",
            self::CACHE_TTL,
            fn () => $this->requestInstallationToken(),
        );
    }

    /**
     * Exchange a signed JWT for a short-lived installation access token.
     */
    private function requestInstallationToken(): string
    {
        $token = Http::withToken($this->generateJwt())
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->baseUrl('https://api.github.com')
            ->post("/app/installations/{$this->installationId}/access_tokens")
            ->throw()
            ->json('token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('GitHub App installation token response did not contain a token.');
        }

        return $token;
    }

    /**
     * Build a short-lived RS256 JWT identifying the GitHub App, as required
     * to request an installation access token. The 'iss' claim accepts
     * either the app's client ID (recommended by GitHub) or its numeric
     * app ID.
     *
     * @see https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/generating-a-json-web-token-jwt-for-a-github-app
     */
    private function generateJwt(): string
    {
        $now = time();

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode([
            // Allow for a bit of clock drift between us and GitHub's servers.
            'iat' => $now - 60,
            'exp' => $now + 600,
            'iss' => $this->clientId,
        ], JSON_THROW_ON_ERROR));

        $signingInput = "{$header}.{$payload}";

        $privateKey = openssl_pkey_get_private($this->privateKey);

        if ($privateKey === false) {
            throw new InvalidArgumentException('Unable to read GitHub App private key: '.openssl_error_string());
        }

        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign GitHub App JWT: '.openssl_error_string());
        }

        return "{$signingInput}.".$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
