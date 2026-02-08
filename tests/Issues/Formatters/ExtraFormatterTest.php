<?php

use Naoray\LaravelGithubMonolog\Issues\Formatters\ExtraFormatter;

beforeEach(function () {
    $this->formatter = new ExtraFormatter;
});

it('returns empty string for empty extra', function () {
    expect($this->formatter->format([]))->toBe('');
});

it('formats extra data as JSON', function () {
    $extra = [
        'key1' => 'value1',
        'key2' => ['nested' => 'data'],
    ];

    $result = $this->formatter->format($extra);

    expect($result)
        ->toContain('"key1": "value1"')
        ->toContain('"key2"')
        ->toContain('"nested": "data"');
});

it('formats complex extra data', function () {
    $extra = [
        'channel' => 'test',
        'level' => 400,
        'datetime' => '2024-01-01 12:00:00',
    ];

    $result = $this->formatter->format($extra);

    expect($result)
        ->toContain('"channel": "test"')
        ->toContain('"level": 400')
        ->toContain('"datetime": "2024-01-01 12:00:00"');
});

it('excludes keys that have dedicated sections', function () {
    $extra = [
        'exception' => ['class' => 'RuntimeException'],
        'environment' => ['app_env' => 'testing'],
        'request' => ['url' => 'https://example.com'],
        'route' => ['name' => 'test.route'],
        'route_summary' => '/dashboard',
        'user' => ['id' => 123],
        'queries' => [],
        'job' => ['name' => 'TestJob'],
        'command' => ['name' => 'test:command'],
        'outgoing_requests' => [],
        'session' => ['data' => []],
        'livewire' => ['component' => 'App\\Livewire\\Counter'],
        'livewire_originating_page' => '/dashboard',
        'inertia' => ['page' => '/dashboard'],
        'custom_key' => 'custom_value',
    ];

    $result = $this->formatter->format($extra);

    expect($result)
        ->not->toContain('exception')
        ->not->toContain('environment')
        ->not->toContain('request')
        ->not->toContain('"route"')
        ->not->toContain('route_summary')
        ->not->toContain('user')
        ->not->toContain('queries')
        ->not->toContain('job')
        ->not->toContain('command')
        ->not->toContain('outgoing_requests')
        ->not->toContain('session')
        ->not->toContain('livewire')
        ->not->toContain('inertia')
        ->toContain('custom_key')
        ->toContain('custom_value');
});

it('returns empty string when all keys are excluded', function () {
    $extra = [
        'environment' => ['app_env' => 'testing'],
        'user' => ['id' => 123],
        'route' => ['name' => 'home'],
    ];

    expect($this->formatter->format($extra))->toBe('');
});
