<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use Monolog\Level;
use Naoray\LaravelGithubMonolog\Tracing\ContextProcessor;

beforeEach(function () {
    $this->processor = new ContextProcessor;
    Context::flush();
});

afterEach(function () {
    Context::flush();
});

it('merges context data into log record context', function () {
    // Arrange
    Context::add('user', ['id' => 123, 'name' => 'John']);
    Context::add('request', ['url' => 'https://example.com', 'method' => 'GET']);

    $record = createLogRecord(
        'Test message',
        ['existing' => 'data'],
        [],
        Level::Error
    );

    // Act
    $processed = ($this->processor)($record);

    // Assert
    expect($processed->context)
        ->toHaveKey('existing')
        ->toHaveKey('user')
        ->toHaveKey('request')
        ->and($processed->context['user'])->toBe(['id' => 123, 'name' => 'John'])
        ->and($processed->context['request'])->toBe(['url' => 'https://example.com', 'method' => 'GET'])
        ->and($processed->context['existing'])->toBe('data');
});

it('preserves existing context when no context data exists', function () {
    // Arrange
    Config::set('logging.channels.github.tracing', [
        'enabled' => true,
        'environment' => false,
        'user' => false,
        'session' => false,
    ]);

    $record = createLogRecord(
        'Test message',
        ['existing' => 'data'],
        [],
        Level::Error
    );

    // Act
    $processed = ($this->processor)($record);

    // Assert
    expect($processed->context)
        ->toHaveKey('existing')
        ->and($processed->context['existing'])->toBe('data')
        ->and($processed->context)->not->toHaveKey('user')
        ->and($processed->context)->not->toHaveKey('request');
});

it('overrides existing context keys with context data', function () {
    // Arrange
    Context::add('user', ['id' => 123]);

    $record = createLogRecord(
        'Test message',
        ['user' => ['id' => 999]],
        [],
        Level::Error
    );

    // Act
    $processed = ($this->processor)($record);

    // Assert
    expect($processed->context['user'])->toBe(['id' => 123]);
});

it('collects environment data when enabled', function () {
    Config::set('logging.channels.github.tracing.environment', true);

    $record = createLogRecord('Test message', [], [], Level::Error);

    $processed = ($this->processor)($record);

    expect($processed->context)->toHaveKey('environment');
    expect($processed->context['environment'])->toHaveKeys(['app_env', 'laravel_version', 'php_version']);
});

it('collects user data on demand when enabled', function () {
    Config::set('logging.channels.github.tracing.user', true);
    Auth::shouldReceive('check')->once()->andReturn(true);
    Auth::shouldReceive('user')->once()->andReturn(Mockery::mock('Illuminate\Contracts\Auth\Authenticatable', function ($mock) {
        $mock->shouldReceive('getAuthIdentifier')->andReturn(1);
    }));

    $record = createLogRecord('Test message', [], [], Level::Error);

    $processed = ($this->processor)($record);

    expect($processed->context)->toHaveKey('user');
});
