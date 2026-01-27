<?php

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\InertiaDataCollector;

beforeEach(function () {
    $this->collector = new InertiaDataCollector;
    config(['github-monolog.tracing.inertia' => true]);
    config(['logging.channels.github.tracing.inertia' => true]);
});

afterEach(function () {
    Context::flush();
});

it('detects inertia request via X-Inertia header', function () {
    $request = Request::create('/dashboard', 'GET');
    $request->headers->set('X-Inertia', 'true');
    $request->headers->set('X-Inertia-Version', 'abc123');
    app()->instance('request', $request);

    $response = new Response(json_encode([
        'component' => 'Dashboard/Index',
        'props' => ['user' => ['name' => 'John']],
    ]));
    $response->headers->set('X-Inertia', 'true');
    $response->headers->set('Content-Type', 'application/json');

    $event = new RequestHandled($request, $response);
    $this->collector->__invoke($event);

    $inertiaData = Context::get('inertia');
    expect($inertiaData)->toBeArray();
    expect($inertiaData['version'])->toBe('abc123');
    expect($inertiaData['component'])->toBe('Dashboard/Index');
    expect($inertiaData['partial_reload'])->toBeFalse();
});

it('skips non-inertia requests', function () {
    $request = Request::create('/api/users', 'GET');
    app()->instance('request', $request);

    $event = new RequestHandled($request, new Response);
    $this->collector->__invoke($event);

    expect(Context::get('inertia'))->toBeNull();
});

it('captures partial reload information', function () {
    $request = Request::create('/dashboard', 'GET');
    $request->headers->set('X-Inertia', 'true');
    $request->headers->set('X-Inertia-Version', 'abc123');
    $request->headers->set('X-Inertia-Partial-Data', 'users,notifications');
    $request->headers->set('X-Inertia-Partial-Component', 'Dashboard/Index');
    app()->instance('request', $request);

    $response = new Response(json_encode([
        'component' => 'Dashboard/Index',
        'props' => ['users' => [], 'notifications' => []],
    ]));
    $response->headers->set('X-Inertia', 'true');

    $event = new RequestHandled($request, $response);
    $this->collector->__invoke($event);

    $inertiaData = Context::get('inertia');
    expect($inertiaData['partial_reload'])->toBeTrue();
    expect($inertiaData['partial_component'])->toBe('Dashboard/Index');
    expect($inertiaData['partial_keys'])->toBe(['users', 'notifications']);
});

it('captures partial except keys', function () {
    $request = Request::create('/dashboard', 'GET');
    $request->headers->set('X-Inertia', 'true');
    $request->headers->set('X-Inertia-Partial-Data', 'users');
    $request->headers->set('X-Inertia-Partial-Except', 'heavy_data,analytics');
    $request->headers->set('X-Inertia-Partial-Component', 'Dashboard/Index');
    app()->instance('request', $request);

    $response = new Response;
    $response->headers->set('X-Inertia', 'true');

    $event = new RequestHandled($request, $response);
    $this->collector->__invoke($event);

    $inertiaData = Context::get('inertia');
    expect($inertiaData['partial_except'])->toBe(['heavy_data', 'analytics']);
});

it('handles response without component in body', function () {
    $request = Request::create('/dashboard', 'GET');
    $request->headers->set('X-Inertia', 'true');
    app()->instance('request', $request);

    // Response without X-Inertia header (initial page load returns HTML)
    $response = new Response('<html>...</html>');

    $event = new RequestHandled($request, $response);
    $this->collector->__invoke($event);

    $inertiaData = Context::get('inertia');
    expect($inertiaData)->toBeArray();
    // Component should not be present since response doesn't have it
    expect($inertiaData)->not->toHaveKey('component');
    expect($inertiaData)->toHaveKey('url');
});

it('returns enabled status based on config', function () {
    config(['logging.channels.github.tracing.inertia' => true]);
    config(['github-monolog.tracing.inertia' => null]);
    expect($this->collector->isEnabled())->toBeTrue();

    config(['logging.channels.github.tracing.inertia' => false]);
    config(['github-monolog.tracing.inertia' => false]);
    expect($this->collector->isEnabled())->toBeFalse();
});

it('includes request url in captured data', function () {
    $request = Request::create('/users/123/edit', 'GET');
    $request->headers->set('X-Inertia', 'true');
    app()->instance('request', $request);

    $response = new Response;

    $event = new RequestHandled($request, $response);
    $this->collector->__invoke($event);

    $inertiaData = Context::get('inertia');
    expect($inertiaData['url'])->toContain('/users/123/edit');
});

it('handles malformed json response gracefully', function () {
    $request = Request::create('/dashboard', 'GET');
    $request->headers->set('X-Inertia', 'true');
    app()->instance('request', $request);

    $response = new Response('not valid json');
    $response->headers->set('X-Inertia', 'true');

    $event = new RequestHandled($request, $response);
    $this->collector->__invoke($event);

    $inertiaData = Context::get('inertia');
    expect($inertiaData)->toBeArray();
    expect($inertiaData)->not->toHaveKey('component');
});
