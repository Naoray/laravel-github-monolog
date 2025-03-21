<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\RequestDataCollector;
use Symfony\Component\HttpFoundation\HeaderBag;

beforeEach(function () {
    $this->collector = new RequestDataCollector;
});

afterEach(function () {
    Context::flush();
});

it('collects request data', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $route = Mockery::mock(Route::class);
    $headers = new HeaderBag([
        'accept' => ['application/json'],
        'cookie' => ['sensitive-cookie'],
        'x-custom' => ['custom-value'],
    ]);

    $request->headers = $headers;
    $request->shouldReceive('url')->once()->andReturn('https://example.com/test');
    $request->shouldReceive('method')->once()->andReturn('POST');
    $request->shouldReceive('all')->once()->andReturn(['key' => 'value']);
    $route->shouldReceive('getName')->once()->andReturn('test.route');

    $event = new RouteMatched($route, $request);

    // Act
    ($this->collector)($event);

    // Assert
    expect(Context::get('request'))->toBe([
        'url' => 'https://example.com/test',
        'method' => 'POST',
        'route' => 'test.route',
        'headers' => [
            'accept' => ['application/json'],
            'cookie' => ['[FILTERED]'],
            'x-custom' => ['custom-value'],
        ],
        'body' => ['key' => 'value'],
    ]);
});

it('filters sensitive headers', function () {
    // Arrange
    $request = Mockery::mock(Request::class);
    $route = Mockery::mock(Route::class);
    $headers = new HeaderBag([
        'XSRF-TOKEN' => ['token123'],
        'remember_web_123' => ['sensitive'],
        'laravel_session' => ['session123'],
        'safe-header' => ['value'],
    ]);

    $request->headers = $headers;
    $request->shouldReceive('url')->once()->andReturn('https://example.com/test');
    $request->shouldReceive('method')->once()->andReturn('GET');
    $request->shouldReceive('all')->once()->andReturn([]);
    $route->shouldReceive('getName')->once()->andReturn('test.route');

    config(['session.cookie' => 'laravel_session']);
    $event = new RouteMatched($route, $request);

    // Act
    ($this->collector)($event);

    // Assert
    expect(Context::get('request')['headers'])->toBe([
        'xsrf-token' => ['[FILTERED]'],
        'remember-web-123' => ['[FILTERED]'],
        'laravel-session' => ['[FILTERED]'],
        'safe-header' => ['value'],
    ]);
});
