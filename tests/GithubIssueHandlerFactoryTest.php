<?php

use Illuminate\Support\Facades\Http;
use Monolog\Level;
use Monolog\Logger;
use Naoray\LaravelGithubMonolog\Deduplication\DeduplicationHandler;
use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;
use Naoray\LaravelGithubMonolog\GithubIssueHandlerFactory;
use Naoray\LaravelGithubMonolog\Issues\Formatters\IssueFormatter;
use Naoray\LaravelGithubMonolog\Issues\Handler;

function generateFactoryTestPrivateKey(): string
{
    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($resource, $privateKey);

    return $privateKey;
}

function getWrappedHandler(DeduplicationHandler $handler): Handler
{
    $reflection = new ReflectionProperty($handler, 'handler');

    return $reflection->getValue($handler);
}

function getCacheManager(DeduplicationHandler $handler): mixed
{
    $reflection = new ReflectionProperty($handler, 'cache');

    return $reflection->getValue($handler);
}

function getSignatureGenerator(DeduplicationHandler $handler): mixed
{
    $reflection = new ReflectionProperty($handler, 'signatureGenerator');

    return $reflection->getValue($handler);
}

beforeEach(function () {
    $this->config = [
        'repo' => 'test/repo',
        'token' => 'test-token',
        'level' => Level::Error,
        'labels' => ['test-label'],
    ];

    $this->factory = app()->make(GithubIssueHandlerFactory::class);
});

test('it creates a logger with deduplication handler', function () {
    $logger = ($this->factory)($this->config);

    expect($logger)
        ->toBeInstanceOf(Logger::class)
        ->and($logger->getHandlers()[0])
        ->toBeInstanceOf(DeduplicationHandler::class);
});

test('it configures handler correctly', function () {
    $logger = ($this->factory)($this->config);

    /** @var DeduplicationHandler $handler */
    $handler = $logger->getHandlers()[0];
    $wrappedHandler = getWrappedHandler($handler);

    expect($wrappedHandler)
        ->toBeInstanceOf(Handler::class)
        ->and($wrappedHandler->getLevel())
        ->toBe(Level::Error)
        ->and($wrappedHandler->getFormatter())
        ->toBeInstanceOf(IssueFormatter::class);
});

test('it throws exception when required config is missing', function () {
    expect(fn () => ($this->factory)([]))->toThrow(InvalidArgumentException::class);
    expect(fn () => ($this->factory)(['repo' => 'test/repo']))->toThrow(InvalidArgumentException::class);
    expect(fn () => ($this->factory)(['token' => 'test-token']))->toThrow(InvalidArgumentException::class);
});

test('it configures buffer settings correctly', function () {
    $this->config['buffer'] = [
        'limit' => 50,
        'flush_on_overflow' => false,
    ];

    $logger = ($this->factory)($this->config);

    /** @var DeduplicationHandler $handler */
    $handler = $logger->getHandlers()[0];
    $bufferLimit = (new ReflectionProperty($handler, 'bufferLimit'))->getValue($handler);
    $flushOnOverflow = (new ReflectionProperty($handler, 'flushOnOverflow'))->getValue($handler);

    expect($bufferLimit)->toBe(50)
        ->and($flushOnOverflow)->toBeFalse();
});

test('it uses configured cache store', function () {
    $logger = ($this->factory)([
        ...$this->config,
        'deduplication' => [
            'store' => 'array',
            'prefix' => 'custom:',
            'time' => 120,
        ],
    ]);

    /** @var DeduplicationHandler $handler */
    $handler = $logger->getHandlers()[0];
    $cacheManager = getCacheManager($handler);
    $store = (new ReflectionProperty($cacheManager, 'store'))->getValue($cacheManager);
    $prefix = (new ReflectionProperty($cacheManager, 'prefix'))->getValue($cacheManager);
    $ttl = (new ReflectionProperty($cacheManager, 'ttl'))->getValue($cacheManager);

    expect($store)->toBe('array')
        ->and($prefix)->toBe('custom:')
        ->and($ttl)->toBe(120);
});

test('it uses default cache configuration', function () {
    $logger = ($this->factory)($this->config);

    /** @var DeduplicationHandler $handler */
    $handler = $logger->getHandlers()[0];
    $cacheManager = getCacheManager($handler);
    $store = (new ReflectionProperty($cacheManager, 'store'))->getValue($cacheManager);
    $prefix = (new ReflectionProperty($cacheManager, 'prefix'))->getValue($cacheManager);
    $ttl = (new ReflectionProperty($cacheManager, 'ttl'))->getValue($cacheManager);

    expect($store)->toBe(config('cache.default'))
        ->and($prefix)->toBe('github-monolog:')
        ->and($ttl)->toBe(60);
});

test('it uses same signature generator across components', function () {
    $logger = ($this->factory)([
        'repo' => 'test/repo',
        'token' => 'test-token',
    ]);

    /** @var DeduplicationHandler $deduplicationHandler */
    $deduplicationHandler = $logger->getHandlers()[0];
    $handler = getWrappedHandler($deduplicationHandler);
    $formatter = $handler->getFormatter();

    // Only check the deduplication handler's signature generator since other components no longer use it
    $deduplicationGenerator = getSignatureGenerator($deduplicationHandler);

    expect($deduplicationGenerator)
        ->toBeInstanceOf(DefaultSignatureGenerator::class);
});

test('it enables occurrence tracking by default', function () {
    $logger = ($this->factory)($this->config);

    /** @var DeduplicationHandler $handler */
    $handler = $logger->getHandlers()[0];
    $trackOccurrences = (new ReflectionProperty($handler, 'trackOccurrences'))->getValue($handler);

    expect($trackOccurrences)->toBeTrue();
});

test('it respects track_occurrences config', function () {
    $logger = ($this->factory)([
        ...$this->config,
        'deduplication' => [
            'track_occurrences' => false,
        ],
    ]);

    /** @var DeduplicationHandler $handler */
    $handler = $logger->getHandlers()[0];
    $trackOccurrences = (new ReflectionProperty($handler, 'trackOccurrences'))->getValue($handler);

    expect($trackOccurrences)->toBeFalse();
});

test('it throws exception for invalid deduplication time', function () {
    expect(fn () => ($this->factory)([
        ...$this->config,
        'deduplication' => [
            'time' => -1,
        ],
    ]))->toThrow(InvalidArgumentException::class, 'Deduplication time must be a positive integer');

    expect(fn () => ($this->factory)([
        ...$this->config,
        'deduplication' => [
            'time' => 'invalid',
        ],
    ]))->toThrow(InvalidArgumentException::class, 'Deduplication time must be a positive integer');
});

test('it creates a handler using github app credentials instead of a token', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/app/installations/*/access_tokens' => Http::response(['token' => 'installation-token']),
    ]);

    $logger = ($this->factory)([
        'repo' => 'test/repo',
        'github_app' => [
            'id' => '12345',
            'installation_id' => '67890',
            'private_key' => generateFactoryTestPrivateKey(),
        ],
    ]);

    /** @var DeduplicationHandler $deduplicationHandler */
    $deduplicationHandler = $logger->getHandlers()[0];
    $handler = getWrappedHandler($deduplicationHandler);

    expect($handler)->toBeInstanceOf(Handler::class);

    $token = (new ReflectionProperty($handler, 'token'))->getValue($handler);

    expect($token)->toBe('installation-token');
});

test('github_app config takes precedence over token when both are present', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/app/installations/*/access_tokens' => Http::response(['token' => 'installation-token']),
    ]);

    $logger = ($this->factory)([
        'repo' => 'test/repo',
        'token' => 'test-token',
        'github_app' => [
            'id' => '12345',
            'installation_id' => '67890',
            'private_key' => generateFactoryTestPrivateKey(),
        ],
    ]);

    $handler = getWrappedHandler($logger->getHandlers()[0]);
    $token = (new ReflectionProperty($handler, 'token'))->getValue($handler);

    expect($token)->toBe('installation-token');
});

test('it throws when github_app config is incomplete and no token is set', function () {
    expect(fn () => ($this->factory)([
        'repo' => 'test/repo',
        'github_app' => [
            'id' => '12345',
        ],
    ]))->toThrow(InvalidArgumentException::class);
});

test('it reads the github app private key from a file path', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/app/installations/*/access_tokens' => Http::response(['token' => 'installation-token']),
    ]);

    $path = tempnam(sys_get_temp_dir(), 'gh-app-key');
    file_put_contents($path, generateFactoryTestPrivateKey());

    try {
        $logger = ($this->factory)([
            'repo' => 'test/repo',
            'github_app' => [
                'id' => '12345',
                'installation_id' => '67890',
                'private_key_path' => $path,
            ],
        ]);

        $handler = getWrappedHandler($logger->getHandlers()[0]);
        $token = (new ReflectionProperty($handler, 'token'))->getValue($handler);

        expect($token)->toBe('installation-token');
    } finally {
        unlink($path);
    }
});
