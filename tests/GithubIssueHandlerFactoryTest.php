<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Monolog\Level;
use Monolog\Logger;
use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\DatabaseStore;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\FileStore;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\RedisStore;
use Naoray\LaravelGithubMonolog\Issues\Formatter;
use Naoray\LaravelGithubMonolog\GithubIssueHandlerFactory;
use Naoray\LaravelGithubMonolog\Deduplication\DeduplicationHandler;
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
    ];
});

test('it creates logger with correct configuration', function () {
    $factory = new GithubIssueHandlerFactory;
    $logger = $factory($this->config);

    expect($logger)
        ->toBeInstanceOf(Logger::class)
        ->and($logger->getName())->toBe('github')
        ->and($logger->getHandlers()[0])
        ->toBeInstanceOf(DeduplicationHandler::class);

    /** @var DeduplicationHandler $deduplicationHandler */
    $deduplicationHandler = $logger->getHandlers()[0];
    $handler = getWrappedHandler($deduplicationHandler);

    expect($handler)
        ->toBeInstanceOf(Handler::class)
        ->and($handler->getFormatter())
        ->toBeInstanceOf(Formatter::class);
});

test('it accepts custom log level', function () {
    $factory = new GithubIssueHandlerFactory;
    $logger = $factory([...$this->config, 'level' => Level::Info]);

    /** @var DeduplicationHandler $handler */
    $handler = $logger->getHandlers()[0];
    expect($handler->getLevel())->toBe(Level::Info);
});

test('it allows custom deduplication configuration', function () {
    $factory = new GithubIssueHandlerFactory;
    $logger = $factory([
        ...$this->config,
        'deduplication' => [
            'driver' => 'database',
            'table' => 'custom_dedup',
            'time' => 300,
        ],
    ]);

    /** @var DeduplicationHandler $handler */
    $handler = $logger->getHandlers()[0];
    $store = getDeduplicationStore($handler);

    expect($store)->toBeInstanceOf(DatabaseStore::class);
});

test('it uses default values for optional config', function () {
    $factory = new GithubIssueHandlerFactory;
    $logger = $factory($this->config);

    /** @var DeduplicationHandler $handler */
    $handler = $logger->getHandlers()[0];
    $store = getDeduplicationStore($handler);

    expect($store)->toBeInstanceOf(RedisStore::class);
});

test('it throws exception for missing required config', function () {
    $factory = new GithubIssueHandlerFactory;

    expect(fn() => $factory([]))->toThrow(\InvalidArgumentException::class);
    expect(fn() => $factory(['repo' => 'test/repo']))->toThrow(\InvalidArgumentException::class);
    expect(fn() => $factory(['token' => 'test-token']))->toThrow(\InvalidArgumentException::class);
});

test('it configures buffer settings correctly', function () {
    $factory = new GithubIssueHandlerFactory;
    $logger = $factory([
        ...$this->config,
        'buffer' => [
            'limit' => 50,
            'flushOnOverflow' => false,
        ],
    ]);

    /** @var DeduplicationHandler $handler */
    $handler = $logger->getHandlers()[0];
    $bufferLimit = (new ReflectionProperty($handler, 'bufferLimit'))->getValue($handler);
    $flushOnOverflow = (new ReflectionProperty($handler, 'flushOnOverflow'))->getValue($handler);

    expect($bufferLimit)->toBe(50)
        ->and($flushOnOverflow)->toBeFalse();
});

test('it can use file store driver', function () {
    $path = sys_get_temp_dir() . '/dedup-test-' . uniqid() . '.log';

    $factory = new GithubIssueHandlerFactory;
    $logger = $factory([
        ...$this->config,
        'deduplication' => [
            'driver' => 'file',
            'path' => $path,
        ],
    ]);

    /** @var DeduplicationHandler $handler */
    $handler = $logger->getHandlers()[0];
    $store = getDeduplicationStore($handler);

    expect($store)
        ->toBeInstanceOf(FileStore::class)
        ->and((new ReflectionProperty($store, 'path'))->getValue($store))
        ->toBe($path);

    expect(file_exists(dirname($path)))->toBeTrue();
    @unlink($path);
});

test('it uses custom prefix for deduplication store', function () {
    $factory = new GithubIssueHandlerFactory;
    $logger = $factory([
        ...$this->config,
        'deduplication' => [
            'prefix' => 'custom-prefix:',
        ],
    ]);

    /** @var DeduplicationHandler $handler */
    $handler = $logger->getHandlers()[0];
    $store = getDeduplicationStore($handler);
    $prefix = (new ReflectionProperty($store, 'prefix'))->getValue($store);

    expect($prefix)->toBe('custom-prefix:');
});

test('it uses same signature generator across components', function () {
    $factory = new GithubIssueHandlerFactory;
    $logger = $factory($this->config);

    /** @var DeduplicationHandler $deduplicationHandler */
    $deduplicationHandler = $logger->getHandlers()[0];
    $handler = getWrappedHandler($deduplicationHandler);
    $formatter = $handler->getFormatter();

    $handlerGenerator = (new ReflectionProperty($handler, 'signatureGenerator'))->getValue($handler);
    $formatterGenerator = (new ReflectionProperty($formatter, 'signatureGenerator'))->getValue($formatter);
    $deduplicationGenerator = getSignatureGenerator($deduplicationHandler);

    expect($handlerGenerator)
        ->toBeInstanceOf(DefaultSignatureGenerator::class)
        ->and($formatterGenerator)
        ->toBeInstanceOf(DefaultSignatureGenerator::class)
        ->and($deduplicationGenerator)
        ->toBeInstanceOf(DefaultSignatureGenerator::class);
});
