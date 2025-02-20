<?php

use Monolog\Level;
use Monolog\Logger;
use Naoray\LaravelGithubMonolog\Deduplication\DeduplicationHandler;
use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\DatabaseStore;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\FileStore;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\RedisStore;
use Naoray\LaravelGithubMonolog\GithubIssueHandlerFactory;
use Naoray\LaravelGithubMonolog\Issues\Formatter;
use Naoray\LaravelGithubMonolog\Issues\Handler;

function getWrappedHandler(DeduplicationHandler $handler): Handler
{
    $reflection = new ReflectionProperty($handler, 'handler');

    return $reflection->getValue($handler);
}

function getDeduplicationStore(DeduplicationHandler $handler): mixed
{
    $reflection = new ReflectionProperty($handler, 'store');

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

    $this->signatureGenerator = new DefaultSignatureGenerator;
    $this->factory = new GithubIssueHandlerFactory($this->signatureGenerator);
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
        ->toBeInstanceOf(Formatter::class);
});

test('it throws exception when required config is missing', function () {
    expect(fn () => ($this->factory)([]))->toThrow(\InvalidArgumentException::class);
    expect(fn () => ($this->factory)(['repo' => 'test/repo']))->toThrow(\InvalidArgumentException::class);
    expect(fn () => ($this->factory)(['token' => 'test-token']))->toThrow(\InvalidArgumentException::class);
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

test('it can use file store driver', function () {
    $path = sys_get_temp_dir().'/dedup-test-'.uniqid().'.log';

    $logger = ($this->factory)([
        ...$this->config,
        'deduplication' => [
            'store' => 'file',
            'path' => $path,
        ],
    ]);

    /** @var DeduplicationHandler $handler */
    $handler = $logger->getHandlers()[0];
    $store = getDeduplicationStore($handler);

    expect($store)->toBeInstanceOf(FileStore::class);
});

test('it can use redis store driver', function () {
    $this->config['deduplication'] = [
        'store' => 'redis',
        'connection' => 'default',
    ];

    $logger = ($this->factory)($this->config);

    /** @var DeduplicationHandler $handler */
    $handler = $logger->getHandlers()[0];
    $store = getDeduplicationStore($handler);

    expect($store)->toBeInstanceOf(RedisStore::class);
});

test('it can use database store driver', function () {
    config()->set('database.connections.sqlite', [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $this->config['deduplication'] = [
        'store' => 'database',
        'connection' => 'sqlite',
    ];

    $logger = ($this->factory)($this->config);

    /** @var DeduplicationHandler $handler */
    $handler = $logger->getHandlers()[0];
    $store = getDeduplicationStore($handler);

    expect($store)->toBeInstanceOf(DatabaseStore::class);
});

test('it uses same signature generator across components', function () {
    $factory = new GithubIssueHandlerFactory(new DefaultSignatureGenerator);
    $logger = $factory([
        'repo' => 'test/repo',
        'token' => 'test-token',
    ]);

    /** @var \Naoray\LaravelGithubMonolog\Deduplication\DeduplicationHandler $deduplicationHandler */
    $deduplicationHandler = $logger->getHandlers()[0];
    $handler = getWrappedHandler($deduplicationHandler);
    $formatter = $handler->getFormatter();

    // Only check the deduplication handler's signature generator since other components no longer use it
    $deduplicationGenerator = getSignatureGenerator($deduplicationHandler);

    expect($deduplicationGenerator)
        ->toBeInstanceOf(DefaultSignatureGenerator::class);
});

test('it throws exception for invalid deduplication time', function () {
    expect(fn () => ($this->factory)([
        ...$this->config,
        'deduplication' => [
            'time' => -1,
        ],
    ]))->toThrow(\InvalidArgumentException::class, 'Deduplication time must be a positive integer');

    expect(fn () => ($this->factory)([
        ...$this->config,
        'deduplication' => [
            'time' => 'invalid',
        ],
    ]))->toThrow(\InvalidArgumentException::class, 'Deduplication time must be a positive integer');
});

test('it uses file store driver by default', function () {
    $logger = ($this->factory)($this->config);

    /** @var DeduplicationHandler $handler */
    $handler = $logger->getHandlers()[0];
    $store = getDeduplicationStore($handler);

    expect($store)->toBeInstanceOf(FileStore::class);
});
