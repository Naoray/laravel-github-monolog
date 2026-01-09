<?php

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Issues\StubLoader;
use Naoray\LaravelGithubMonolog\Issues\TemplateRenderer;
use Naoray\LaravelGithubMonolog\Tracing\ContextProcessor;
use Naoray\LaravelGithubMonolog\Tracing\RequestDataCollector;
use Naoray\LaravelGithubMonolog\Tracing\RouteDataCollector;

beforeEach(function () {
    $this->stubLoader = new StubLoader;
    $this->renderer = resolve(TemplateRenderer::class);
    $this->processor = new ContextProcessor;
    $this->requestCollector = new RequestDataCollector;
    $this->routeCollector = new RouteDataCollector;
    Context::flush();
});

afterEach(function () {
    Context::flush();
});

it('includes request route information in context section', function () {
    // Arrange - Simulate route matched and request handled events
    $request = Request::create('https://example.com/api/users', 'GET');
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getName')->andReturn('api.users.index');
    $route->shouldReceive('uri')->andReturn('api/users');
    $route->shouldReceive('parameters')->andReturn([]);
    $route->shouldReceive('getAction')->andReturn([]);
    $route->shouldReceive('gatherMiddleware')->andReturn(['web']);
    $route->shouldReceive('methods')->andReturn(['GET', 'HEAD']);

    $routeEvent = new RouteMatched($route, $request);
    ($this->routeCollector)($routeEvent);

    $requestEvent = new RequestHandled($request, Mockery::mock('Illuminate\Http\Response'));
    ($this->requestCollector)($requestEvent);

    // Verify route and request data are in Context
    expect(Context::get('route'))->toHaveKey('name');
    expect(Context::get('route')['name'])->toBe('api.users.index');
    expect(Context::get('request'))->toHaveKey('url');

    $record = createLogRecord('Test error');
    $record = ($this->processor)($record);

    // Verify request and route data are in the record after processing
    expect($record->context)->toHaveKey('request')->toHaveKey('route');

    // Act
    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    // Assert
    expect($rendered)
        ->toContain('<summary>📥 Request</summary>')
        ->toContain('<summary>🛣️ Route Details</summary>')
        ->toContain('"name": "api.users.index"')
        ->toContain('"url"')
        ->toContain('"method": "GET"');
});

it('includes both user and request data in context section', function () {
    // Arrange
    Context::add('user', ['id' => 123, 'email' => 'user@example.com']);

    $request = Request::create('https://example.com/api/posts', 'POST', ['title' => 'Test']);
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getName')->andReturn('api.posts.store');
    $route->shouldReceive('uri')->andReturn('api/posts');
    $route->shouldReceive('parameters')->andReturn([]);
    $route->shouldReceive('getAction')->andReturn([]);
    $route->shouldReceive('gatherMiddleware')->andReturn(['web']);
    $route->shouldReceive('methods')->andReturn(['POST']);

    $routeEvent = new RouteMatched($route, $request);
    ($this->routeCollector)($routeEvent);

    $requestEvent = new RequestHandled($request, Mockery::mock('Illuminate\Http\Response'));
    ($this->requestCollector)($requestEvent);

    // Verify both user, route and request data are in Context
    expect(Context::get('user'))->toBe(['id' => 123, 'email' => 'user@example.com']);
    expect(Context::get('route'))->toHaveKey('name');
    expect(Context::get('route')['name'])->toBe('api.posts.store');
    expect(Context::get('request'))->toHaveKey('url');

    $record = createLogRecord('Test error');
    $record = ($this->processor)($record);

    // Verify all are in the record after processing
    expect($record->context)->toHaveKey('user')->toHaveKey('request')->toHaveKey('route');

    // Act
    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    // Assert - All should be in their respective sections
    expect($rendered)
        ->toContain('<summary>👤 User Details</summary>')
        ->toContain('<summary>📥 Request</summary>')
        ->toContain('<summary>🛣️ Route Details</summary>')
        ->toContain('"id": 123')
        ->toContain('"email": "user@example.com"')
        ->toContain('"name": "api.posts.store"')
        ->toContain('"method": "POST"');
});
