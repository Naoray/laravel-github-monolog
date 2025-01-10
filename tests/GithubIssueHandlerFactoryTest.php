<?php

use Monolog\Level;
use Monolog\Logger;
use Naoray\LaravelGithubMonolog\GithubIssueFormatter;
use Naoray\LaravelGithubMonolog\GithubIssueHandlerFactory;
use Naoray\LaravelGithubMonolog\GithubIssueLoggerHandler;

test('it creates logger with correct configuration', function () {
    $config = [
        'repo' => 'test/repo',
        'token' => 'fake-token',
        'level' => Level::Error->value,
        'labels' => ['bug', 'automated'],
    ];

    $factory = new GithubIssueHandlerFactory;
    $handler = $factory($config);

    expect($logger)
        ->toBeInstanceOf(Logger::class)
        ->and($logger->getName())->toBe('github')
        ->and($logger->getHandlers()[0])
        ->toBeInstanceOf(GithubIssueLoggerHandler::class);
});

test('it throws exception for missing required config', function () {
    $factory = new GithubIssueHandlerFactory;

    expect(fn() => $factory([]))
        ->toThrow(InvalidArgumentException::class, 'GitHub repository is required');

    expect(fn() => $factory(['repo' => 'test/repo']))
        ->toThrow(InvalidArgumentException::class, 'GitHub token is required');
});

test('it uses default values for optional config', function () {
    $config = [
        'repo' => 'test/repo',
        'token' => 'fake-token',
    ];

    $factory = new GithubIssueHandlerFactory;
    $handler = $factory($config);

    expect($handler)
        ->toBeInstanceOf(GithubIssueLoggerHandler::class)
        ->and($handler->getLevel())->toBe(Level::Error)
        ->and($handler->getBubble())->toBeTrue()
        ->and($handler->getFormatter())->toBeInstanceOf(GithubIssueFormatter::class);
});

test('it accepts custom log level', function () {
    $config = [
        'repo' => 'test/repo',
        'token' => 'fake-token',
        'level' => Level::Debug->value,
    ];

    $factory = new GithubIssueHandlerFactory;
    $handler = $factory($config);

    expect($handler->getLevel())->toBe(Level::Debug);
});
