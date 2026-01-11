<?php

use Monolog\Level;
use Naoray\LaravelGithubMonolog\Deduplication\SignatureContextExtractor;
use Naoray\LaravelGithubMonolog\Deduplication\SignatureContextKind;

beforeEach(function () {
    $this->extractor = new SignatureContextExtractor;
});

test('detects HTTP context from request.route array', function () {
    $record = createLogRecord('Test', [
        'request' => [
            'route' => ['name' => 'api.users.index', 'uri' => 'api/users'],
            'method' => 'GET',
        ],
    ]);

    $context = $this->extractor->extract($record);

    expect($context['kind'])->toBe(SignatureContextKind::Http->value);
    expect($context['data'])->toHaveKey('method', 'GET');
    expect($context['data'])->toHaveKey('route', 'api.users.index');
});

test('detects HTTP context from route array', function () {
    $record = createLogRecord('Test', [
        'route' => ['name' => 'api.posts.index', 'uri' => 'api/posts'],
        'request' => ['method' => 'POST'],
    ]);

    $context = $this->extractor->extract($record);

    expect($context['kind'])->toBe(SignatureContextKind::Http->value);
    expect($context['data'])->toHaveKey('method', 'POST');
    expect($context['data'])->toHaveKey('route', 'api.posts.index');
});

test('detects HTTP context from request.method only', function () {
    $record = createLogRecord('Test', [
        'request' => ['method' => 'PUT'],
    ]);

    $context = $this->extractor->extract($record);

    expect($context['kind'])->toBe(SignatureContextKind::Http->value);
    expect($context['data'])->toHaveKey('method', 'PUT');
});

test('detects HTTP context from http.method', function () {
    $record = createLogRecord('Test', [
        'http' => ['method' => 'DELETE'],
    ]);

    $context = $this->extractor->extract($record);

    expect($context['kind'])->toBe(SignatureContextKind::Http->value);
    expect($context['data'])->toHaveKey('method', 'DELETE');
});

test('prefers route name over uri template', function () {
    $record = createLogRecord('Test', [
        'route' => [
            'name' => 'api.users.show',
            'uri' => 'api/users/{id}',
        ],
        'request' => ['method' => 'GET'],
    ]);

    $context = $this->extractor->extract($record);

    expect($context['data']['route'])->toBe('api.users.show');
});

test('falls back to uri template when route name is missing', function () {
    $record = createLogRecord('Test', [
        'route' => [
            'uri' => 'api/users/{id}',
        ],
        'request' => ['method' => 'GET'],
    ]);

    $context = $this->extractor->extract($record);

    expect($context['data']['route'])->toBe('api/users/{id}');
});

test('includes controller in HTTP context when available', function () {
    $record = createLogRecord('Test', [
        'route' => [
            'name' => 'api.users.index',
            'controller' => 'App\\Http\\Controllers\\UserController',
        ],
        'request' => ['method' => 'GET'],
    ]);

    $context = $this->extractor->extract($record);

    expect($context['data'])->toHaveKey('controller', 'App\\Http\\Controllers\\UserController');
});

test('extracts controller class from action string', function () {
    $record = createLogRecord('Test', [
        'route' => [
            'name' => 'api.users.index',
            'controller' => 'App\\Http\\Controllers\\UserController@index',
        ],
        'request' => ['method' => 'GET'],
    ]);

    $context = $this->extractor->extract($record);

    expect($context['data'])->toHaveKey('controller', 'App\\Http\\Controllers\\UserController');
});

test('detects Job context from job.class', function () {
    $record = createLogRecord('Test', [
        'job' => ['class' => 'App\\Jobs\\ProcessOrder'],
    ]);

    $context = $this->extractor->extract($record);

    expect($context['kind'])->toBe(SignatureContextKind::Job->value);
    expect($context['data'])->toHaveKey('job', 'App\\Jobs\\ProcessOrder');
});

test('includes queue name in Job context when available', function () {
    $record = createLogRecord('Test', [
        'job' => [
            'class' => 'App\\Jobs\\ProcessOrder',
            'queue' => 'high-priority',
        ],
    ]);

    $context = $this->extractor->extract($record);

    expect($context['data'])->toHaveKey('queue', 'high-priority');
});

test('detects Command context from command.name', function () {
    $record = createLogRecord('Test', [
        'command' => ['name' => 'import:users'],
    ]);

    $context = $this->extractor->extract($record);

    expect($context['kind'])->toBe(SignatureContextKind::Command->value);
    expect($context['data'])->toHaveKey('command', 'import:users');
});

test('detects Other context when no specific context is present', function () {
    $record = createLogRecord('Test', []);

    $context = $this->extractor->extract($record);

    expect($context['kind'])->toBe(SignatureContextKind::Other->value);
    expect($context['data'])->toHaveKey('channel');
    expect($context['data'])->toHaveKey('level');
});

test('excludes level from Other context when exception is present', function () {
    $record = createLogRecord('Test', [], exception: new \Exception('Test exception'));

    $context = $this->extractor->extract($record);

    expect($context['kind'])->toBe(SignatureContextKind::Other->value);
    expect($context['data'])->toHaveKey('channel');
    expect($context['data'])->not->toHaveKey('level');
});

test('prioritizes HTTP over Job', function () {
    $record = createLogRecord('Test', [
        'request' => ['method' => 'GET'],
        'job' => ['class' => 'App\\Jobs\\ProcessOrder'],
    ]);

    $context = $this->extractor->extract($record);

    expect($context['kind'])->toBe(SignatureContextKind::Http->value);
});

test('prioritizes Job over Command', function () {
    $record = createLogRecord('Test', [
        'job' => ['class' => 'App\\Jobs\\ProcessOrder'],
        'command' => ['name' => 'import:users'],
    ]);

    $context = $this->extractor->extract($record);

    expect($context['kind'])->toBe(SignatureContextKind::Job->value);
});

test('prioritizes Command over Other', function () {
    $record = createLogRecord('Test', [
        'command' => ['name' => 'import:users'],
    ]);

    $context = $this->extractor->extract($record);

    expect($context['kind'])->toBe(SignatureContextKind::Command->value);
});

test('uppercases HTTP method', function () {
    $record = createLogRecord('Test', [
        'request' => ['method' => 'post'],
    ]);

    $context = $this->extractor->extract($record);

    expect($context['data']['method'])->toBe('POST');
});
