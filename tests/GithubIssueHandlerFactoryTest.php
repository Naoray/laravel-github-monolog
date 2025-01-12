<?php

use Monolog\Handler\DeduplicationHandler;
use Monolog\Level;
use Monolog\Logger;
use Naoray\LaravelGithubMonolog\Formatters\GithubIssueFormatter;
use Naoray\LaravelGithubMonolog\GithubIssueHandlerFactory;
use Naoray\LaravelGithubMonolog\Handlers\IssueLogHandler;

function getWrappedHandler(DeduplicationHandler $handler): IssueLogHandler
{
    $reflection = new ReflectionProperty($handler, 'handler');
    $reflection->setAccessible(true);

    return $reflection->getValue($handler);
}

function getDeduplicationStore(DeduplicationHandler $handler): string
{
    $reflection = new ReflectionProperty($handler, 'deduplicationStore');
    $reflection->setAccessible(true);

    return $reflection->getValue($handler);
}

test('it creates logger with correct configuration', function () {
    $config = [
        'repo' => 'test/repo',
        'token' => 'fake-token',
        'level' => Level::Error->value,
        'labels' => ['bug', 'automated'],
    ];

    $factory = new GithubIssueHandlerFactory;
    $logger = $factory($config);

    expect($logger)
        ->toBeInstanceOf(Logger::class)
        ->and($logger->getName())->toBe('github')
        ->and($logger->getHandlers()[0])
        ->toBeInstanceOf(DeduplicationHandler::class);

    /** @var DeduplicationHandler $deduplicationHandler */
    $deduplicationHandler = $logger->getHandlers()[0];
    $handler = getWrappedHandler($deduplicationHandler);

    expect($handler)->toBeInstanceOf(IssueLogHandler::class);
});

test('it throws exception for missing required config', function () {
    $factory = new GithubIssueHandlerFactory;

    expect(fn () => $factory([]))
        ->toThrow(InvalidArgumentException::class, 'GitHub repository is required');

    expect(fn () => $factory(['repo' => 'test/repo']))
        ->toThrow(InvalidArgumentException::class, 'GitHub token is required');
});

test('it uses default values for optional config', function () {
    $config = [
        'repo' => 'test/repo',
        'token' => 'fake-token',
    ];

    $factory = new GithubIssueHandlerFactory;
    $logger = $factory($config);

    /** @var DeduplicationHandler $deduplicationHandler */
    $deduplicationHandler = $logger->getHandlers()[0];
    $handler = getWrappedHandler($deduplicationHandler);

    expect($handler)
        ->toBeInstanceOf(IssueLogHandler::class)
        ->and($handler->getLevel())->toBe(Level::Error)
        ->and($handler->getBubble())->toBeTrue()
        ->and($handler->getFormatter())->toBeInstanceOf(GithubIssueFormatter::class);

    // Verify default deduplication store path
    expect(getDeduplicationStore($deduplicationHandler))
        ->toBe(storage_path('logs/github-issues-dedup.log'));
});

test('it accepts custom log level', function () {
    $config = [
        'repo' => 'test/repo',
        'token' => 'fake-token',
        'level' => Level::Debug->value,
    ];

    $factory = new GithubIssueHandlerFactory;
    $logger = $factory($config);

    /** @var DeduplicationHandler $deduplicationHandler */
    $deduplicationHandler = $logger->getHandlers()[0];
    $handler = getWrappedHandler($deduplicationHandler);

    expect($handler->getLevel())->toBe(Level::Debug);
});

test('it allows custom deduplication configuration', function () {
    $config = [
        'repo' => 'test/repo',
        'token' => 'fake-token',
        'deduplication' => [
            'store' => '/custom/path/dedup.log',
            'time' => 120,
        ],
    ];

    $factory = new GithubIssueHandlerFactory;
    $logger = $factory($config);

    /** @var DeduplicationHandler $deduplicationHandler */
    $deduplicationHandler = $logger->getHandlers()[0];

    expect(getDeduplicationStore($deduplicationHandler))
        ->toBe('/custom/path/dedup.log');

    $reflection = new ReflectionProperty($deduplicationHandler, 'time');
    $reflection->setAccessible(true);
    expect($reflection->getValue($deduplicationHandler))->toBe(120);
});
