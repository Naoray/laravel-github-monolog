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
    $route->shouldReceive('getName')->andReturn('users.index');
    $route->shouldReceive('uri')->andReturn('users');
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
    $route->shouldReceive('getName')->andReturn(null);
    $route->shouldReceive('uri')->andReturn('api/users');
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

it('sets route_summary for normal routes', function () {
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getName')->andReturn('dashboard');
    $route->shouldReceive('uri')->andReturn('dashboard');
    $route->shouldReceive('parameters')->once()->andReturn([]);
    $route->shouldReceive('getAction')->once()->andReturn([]);
    $route->shouldReceive('gatherMiddleware')->once()->andReturn([]);
    $route->shouldReceive('methods')->once()->andReturn(['GET']);

    $request = Mockery::mock('Illuminate\Http\Request');
    $event = new RouteMatched($route, $request);

    ($this->collector)($event);

    $routeSummary = Context::get('route_summary');
    expect($routeSummary)->toBe('dashboard');
});

it('uses originating page for livewire routes', function () {
    // Set up the originating page context (as would be set by LivewireDataCollector)
    Context::add('livewire_originating_page', '/dashboard');

    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getName')->andReturn(null);
    $route->shouldReceive('uri')->andReturn('livewire/message/counter');
    $route->shouldReceive('parameters')->once()->andReturn([]);
    $route->shouldReceive('getAction')->once()->andReturn([]);
    $route->shouldReceive('gatherMiddleware')->once()->andReturn([]);
    $route->shouldReceive('methods')->once()->andReturn(['POST']);

    $request = Mockery::mock('Illuminate\Http\Request');
    $event = new RouteMatched($route, $request);

    ($this->collector)($event);

    $routeSummary = Context::get('route_summary');
    expect($routeSummary)->toBe('/dashboard');
});

it('identifies livewire message routes', function () {
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getName')->andReturn(null);
    $route->shouldReceive('uri')->andReturn('livewire/message/some-component');
    $route->shouldReceive('parameters')->once()->andReturn([]);
    $route->shouldReceive('getAction')->once()->andReturn([]);
    $route->shouldReceive('gatherMiddleware')->once()->andReturn([]);
    $route->shouldReceive('methods')->once()->andReturn(['POST']);

    $request = Mockery::mock('Illuminate\Http\Request');
    $event = new RouteMatched($route, $request);

    ($this->collector)($event);

    // For Livewire routes without originating page, it should try referer
    // When no referer available, it falls back to the URI
    $routeSummary = Context::get('route_summary');
    expect($routeSummary)->toBeString();
});

it('identifies livewire update routes', function () {
    $route = Mockery::mock(Route::class);
    $route->shouldReceive('getName')->andReturn(null);
    $route->shouldReceive('uri')->andReturn('livewire/update');
    $route->shouldReceive('parameters')->once()->andReturn([]);
    $route->shouldReceive('getAction')->once()->andReturn([]);
    $route->shouldReceive('gatherMiddleware')->once()->andReturn([]);
    $route->shouldReceive('methods')->once()->andReturn(['POST']);

    $request = Mockery::mock('Illuminate\Http\Request');
    $event = new RouteMatched($route, $request);

    ($this->collector)($event);

    // Livewire update route should be identified
    $routeData = Context::get('route');
    expect($routeData['uri'])->toBe('livewire/update');
});
