<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Naoray\LaravelGithubMonolog\Auth\GithubAppTokenProvider;

function generateTestPrivateKey(): string
{
    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($resource, $privateKey);

    return $privateKey;
}

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::store('array')->flush();
});

test('it requests and returns an installation access token', function () {
    Http::fake([
        'api.github.com/app/installations/*/access_tokens' => Http::response(['token' => 'installation-token']),
    ]);

    $provider = new GithubAppTokenProvider(
        appId: '12345',
        installationId: '67890',
        privateKey: generateTestPrivateKey(),
        cacheStore: 'array',
    );

    expect($provider->getToken())->toBe('installation-token');

    Http::assertSent(function (Request $request) {
        return str($request->url())->endsWith('/app/installations/67890/access_tokens')
            && $request->hasHeader('Authorization');
    });
});

test('it signs the JWT used to request the installation token', function () {
    Http::fake([
        'api.github.com/app/installations/*/access_tokens' => Http::response(['token' => 'installation-token']),
    ]);

    $privateKey = generateTestPrivateKey();
    $publicKey = openssl_pkey_get_details(openssl_pkey_get_private($privateKey))['key'];

    $provider = new GithubAppTokenProvider(
        appId: '12345',
        installationId: '67890',
        privateKey: $privateKey,
        cacheStore: 'array',
    );

    $provider->getToken();

    Http::assertSent(function (Request $request) use ($publicKey) {
        $jwt = str($request->header('Authorization')[0])->after('Bearer ')->toString();
        [$header, $payload, $signature] = explode('.', $jwt);

        $decodedPayload = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

        $verified = openssl_verify(
            "{$header}.{$payload}",
            base64_decode(strtr($signature, '-_', '+/')),
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        return $verified === 1 && $decodedPayload['iss'] === '12345';
    });
});

test('it caches the installation token', function () {
    Http::fake([
        'api.github.com/app/installations/*/access_tokens' => Http::response(['token' => 'installation-token']),
    ]);

    $provider = new GithubAppTokenProvider(
        appId: '12345',
        installationId: '67890',
        privateKey: generateTestPrivateKey(),
        cacheStore: 'array',
    );

    $provider->getToken();
    $provider->getToken();

    Http::assertSentCount(1);
});

test('it throws when the private key is invalid', function () {
    $provider = new GithubAppTokenProvider(
        appId: '12345',
        installationId: '67890',
        privateKey: 'not-a-valid-key',
        cacheStore: 'array',
    );

    expect(fn () => $provider->getToken())->toThrow(InvalidArgumentException::class);
});

test('it throws when the response does not contain a token', function () {
    Http::fake([
        'api.github.com/app/installations/*/access_tokens' => Http::response(['foo' => 'bar']),
    ]);

    $provider = new GithubAppTokenProvider(
        appId: '12345',
        installationId: '67890',
        privateKey: generateTestPrivateKey(),
        cacheStore: 'array',
    );

    expect(fn () => $provider->getToken())->toThrow(RuntimeException::class);
});
