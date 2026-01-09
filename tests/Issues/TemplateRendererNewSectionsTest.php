<?php

use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Issues\StubLoader;
use Naoray\LaravelGithubMonolog\Issues\TemplateRenderer;
use Naoray\LaravelGithubMonolog\Tracing\ContextProcessor;

beforeEach(function () {
    $this->stubLoader = new StubLoader;
    $this->renderer = resolve(TemplateRenderer::class);
    $this->processor = new ContextProcessor;
    Context::flush();
});

afterEach(function () {
    Context::flush();
});

it('renders environment section when environment data exists', function () {
    Context::add('environment', [
        'app_env' => 'testing',
        'laravel_version' => '11.0.0',
        'php_version' => '8.2.0',
    ]);

    $record = createLogRecord('Test message');
    $record = ($this->processor)($record);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('## Environment')
        ->toContain('"app_env": "testing"')
        ->toContain('"laravel_version": "11.0.0"')
        ->toContain('"php_version": "8.2.0"');
});

it('renders request section when request data exists', function () {
    Context::add('request', [
        'url' => 'https://example.com/test',
        'method' => 'POST',
        'ip' => '127.0.0.1',
    ]);

    $record = createLogRecord('Test message');
    $record = ($this->processor)($record);

    // Verify data is in the record
    expect($record->context)->toHaveKey('request');
    expect($record->context['request'])->toHaveKey('url');
    expect($record->context['request']['url'])->toBe('https://example.com/test');

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('## Request')
        ->toContain('example.com')
        ->toContain('"method": "POST"');
});

it('renders route section when route data exists', function () {
    Context::add('route', [
        'name' => 'users.show',
        'uri' => 'users/{id}',
        'controller' => 'App\Http\Controllers\UserController@show',
        'middleware' => ['web', 'auth'],
    ]);

    $record = createLogRecord('Test message');
    $record = ($this->processor)($record);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('## Route')
        ->toContain('users.show')
        ->toContain('UserController@show')
        ->toContain('middleware');
});

it('renders user section when user data exists', function () {
    Context::add('user', [
        'id' => 123,
        'email' => 'user@example.com',
        'authenticated' => true,
    ]);

    $record = createLogRecord('Test message');
    $record = ($this->processor)($record);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('## User')
        ->toContain('"id": 123')
        ->toContain('"email": "user@example.com"')
        ->toContain('"authenticated": true');
});

it('renders queries section when queries exist', function () {
    Context::add('queries', [
        [
            'sql' => 'SELECT * FROM users WHERE id = ?',
            'bindings' => [1],
            'time' => 10.5,
            'connection' => 'mysql',
        ],
    ]);

    $record = createLogRecord('Test message');
    $record = ($this->processor)($record);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('## Recent Queries')
        ->toContain('SELECT * FROM users WHERE id = ?')
        ->toContain('mysql')
        ->toContain('10.5');
});

it('renders job section when job data exists', function () {
    Context::add('job', [
        'name' => 'App\Jobs\ProcessOrder',
        'queue' => 'default',
        'attempts' => 2,
    ]);

    $record = createLogRecord('Test message');
    $record = ($this->processor)($record);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('## Job Context')
        ->toContain('ProcessOrder')
        ->toContain('default')
        ->toContain('2');
});

it('renders command section when command data exists', function () {
    Context::add('command', [
        'name' => 'test:command',
        'arguments' => ['arg1' => 'value1'],
        'options' => ['--option' => 'value2'],
    ]);

    $record = createLogRecord('Test message');
    $record = ($this->processor)($record);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('## Command Context')
        ->toContain('"name": "test:command"')
        ->toContain('"arguments"')
        ->toContain('"options"');
});

it('renders outgoing requests section when outgoing requests exist', function () {
    Context::add('outgoing_requests', [
        [
            'url' => 'https://api.example.com/test',
            'method' => 'GET',
            'status' => 200,
            'duration_ms' => 150.5,
        ],
    ]);

    $record = createLogRecord('Test message');
    $record = ($this->processor)($record);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('## Outgoing Requests')
        ->toContain('GET https://api.example.com/test')
        ->toContain('200')
        ->toContain('150.5');
});

it('renders session section when session data exists', function () {
    Context::add('session', [
        'data' => ['key' => 'value'],
        'flash' => ['old' => [], 'new' => []],
    ]);

    $record = createLogRecord('Test message');
    $record = ($this->processor)($record);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('## Session')
        ->toContain('"data"')
        ->toContain('"flash"');
});

it('removes empty sections from rendered template', function () {
    $record = createLogRecord('Test message');
    $record = ($this->processor)($record);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->not->toContain('<!-- environment:start -->')
        ->not->toContain('<!-- request:start -->')
        ->not->toContain('<!-- user:start -->')
        ->not->toContain('<!-- queries:start -->')
        ->not->toContain('<!-- job:start -->')
        ->not->toContain('<!-- command:start -->')
        ->not->toContain('<!-- outgoing_requests:start -->')
        ->not->toContain('<!-- session:start -->');
});

it('formats multiple queries correctly', function () {
    Context::add('queries', [
        [
            'sql' => 'SELECT * FROM users',
            'bindings' => [],
            'time' => 5.0,
            'connection' => 'mysql',
        ],
        [
            'sql' => 'UPDATE posts SET views = ?',
            'bindings' => [100],
            'time' => 12.3,
            'connection' => 'mysql',
        ],
    ]);

    $record = createLogRecord('Test message');
    $record = ($this->processor)($record);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('SELECT * FROM users')
        ->toContain('UPDATE posts SET views = ?')
        ->toContain('5.0ms')
        ->toContain('12.3ms');
});

it('formats multiple outgoing requests correctly', function () {
    Context::add('outgoing_requests', [
        [
            'url' => 'https://api.example.com/users',
            'method' => 'GET',
            'status' => 200,
            'duration_ms' => 100.0,
        ],
        [
            'url' => 'https://api.example.com/posts',
            'method' => 'POST',
            'status' => 201,
            'duration_ms' => 250.5,
        ],
    ]);

    $record = createLogRecord('Test message');
    $record = ($this->processor)($record);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('GET https://api.example.com/users')
        ->toContain('POST https://api.example.com/posts')
        ->toContain('200')
        ->toContain('201')
        ->toContain('100')
        ->toContain('250.5');
});
