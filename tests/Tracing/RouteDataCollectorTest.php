<?php

use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\RouteDataCollector;

beforeEach(function () {
    $this->collector = new RouteDataCollector;
});

afterEach(function () {
    Context::flush();
});

it('collects route data', function () {
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getName')->once()->andReturn('users.index');
    $route->shouldReceive('uri')->once()->andReturn('users');
    $route->shouldReceive('parameters')->once()->andReturn(['id' => 123]);
    $route->shouldReceive('getAction')->once()->andReturn([
        'controller' => 'App\Http\Controllers\UserController@index',
    ]);
    $route->shouldReceive('gatherMiddleware')->once()->andReturn(['web', 'auth']);
    $route->shouldReceive('methods')->once()->andReturn(['GET', 'HEAD']);

    $request = Mockery::mock('Illuminate\Http\Request');
    $event = new RouteMatched($route, $request);

    ($this->collector)($event);

    $routeData = Context::get('route');

    expect($routeData)->toHaveKeys(['name', 'uri', 'parameters', 'controller', 'middleware', 'methods']);
    expect($routeData['name'])->toBe('users.index');
    expect($routeData['uri'])->toBe('users');
    expect($routeData['parameters'])->toBe(['id' => 123]);
    expect($routeData['controller'])->toBe('App\Http\Controllers\UserController@index');
    expect($routeData['middleware'])->toBe(['web', 'auth']);
    expect($routeData['methods'])->toBe(['GET', 'HEAD']);
});

it('handles route without name', function () {
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getName')->once()->andReturn(null);
    $route->shouldReceive('uri')->once()->andReturn('api/users');
    $route->shouldReceive('parameters')->once()->andReturn([]);
    $route->shouldReceive('getAction')->once()->andReturn([]);
    $route->shouldReceive('gatherMiddleware')->once()->andReturn([]);
    $route->shouldReceive('methods')->once()->andReturn(['GET']);

    $request = Mockery::mock('Illuminate\Http\Request');
    $event = new RouteMatched($route, $request);

    ($this->collector)($event);

    $routeData = Context::get('route');

    expect($routeData['name'])->toBeNull();
    expect($routeData['controller'])->toBeNull();
});
