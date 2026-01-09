<?php

use Naoray\LaravelGithubMonolog\Issues\Formatters\ContextFormatter;

beforeEach(function () {
    $this->formatter = new ContextFormatter;
});

it('returns empty string for empty context', function () {
    expect($this->formatter->format([]))->toBe('');
});

it('excludes exception from formatted context', function () {
    $context = [
        'exception' => new RuntimeException('Test'),
        'custom_key' => 'custom_value',
    ];

    $result = $this->formatter->format($context);

    expect($result)
        ->not->toContain('exception')
        ->toContain('custom_key')
        ->toContain('custom_value');
});

it('excludes separate sections from formatted context', function () {
    $context = [
        'environment' => ['app_env' => 'testing'],
        'request' => ['url' => 'https://example.com'],
        'route' => ['name' => 'test.route'],
        'user' => ['id' => 123],
        'queries' => [],
        'job' => ['name' => 'TestJob'],
        'command' => ['name' => 'test:command'],
        'outgoing_requests' => [],
        'session' => ['data' => []],
        'custom_key' => 'custom_value',
    ];

    $result = $this->formatter->format($context);

    expect($result)
        ->not->toContain('environment')
        ->not->toContain('request')
        ->not->toContain('route')
        ->not->toContain('user')
        ->not->toContain('queries')
        ->not->toContain('job')
        ->not->toContain('command')
        ->not->toContain('outgoing_requests')
        ->not->toContain('session')
        ->toContain('custom_key')
        ->toContain('custom_value');
});

it('formats remaining context as JSON', function () {
    $context = [
        'key1' => 'value1',
        'key2' => ['nested' => 'data'],
    ];

    $result = $this->formatter->format($context);

    expect($result)
        ->toContain('"key1": "value1"')
        ->toContain('"key2"')
        ->toContain('"nested": "data"');
});
