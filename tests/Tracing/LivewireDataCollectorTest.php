<?php

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\LivewireDataCollector;

beforeEach(function () {
    $this->collector = new LivewireDataCollector;
    config(['github-monolog.tracing.livewire' => true]);
    config(['logging.channels.github.tracing.livewire' => true]);
});

afterEach(function () {
    Context::flush();
});

it('detects livewire request via X-Livewire header', function () {
    $request = Request::create('/livewire/update', 'POST');
    $request->headers->set('X-Livewire', 'true');
    $request->headers->set('Content-Type', 'application/json');

    $payload = [
        'components' => [
            [
                'snapshot' => json_encode([
                    'memo' => [
                        'name' => 'user-profile',
                        'id' => 'abc123',
                        'path' => '/dashboard',
                    ],
                ]),
                'calls' => [
                    ['method' => 'save'],
                ],
                'updates' => [
                    'name' => 'John Doe',
                ],
            ],
        ],
    ];

    $request->merge($payload);
    app()->instance('request', $request);

    $event = new RequestHandled($request, new Response);
    $this->collector->__invoke($event);

    $livewireData = Context::get('livewire');
    expect($livewireData)->toBeArray();
    expect($livewireData)->toHaveKey('components');
    expect($livewireData['components'][0])->toHaveKey('name');
    expect($livewireData['components'][0]['name'])->toBe('user-profile');
    expect($livewireData['components'][0]['methods'])->toBe(['save']);
    expect($livewireData['components'][0]['updates'])->toBe(['name']);
});

it('detects livewire request via path containing livewire/update', function () {
    $request = Request::create('/livewire/update', 'POST');
    $request->headers->set('Content-Type', 'application/json');

    $payload = [
        'components' => [
            [
                'snapshot' => [
                    'memo' => [
                        'name' => 'counter',
                        'id' => 'xyz789',
                    ],
                ],
            ],
        ],
    ];

    $request->merge($payload);
    app()->instance('request', $request);

    $event = new RequestHandled($request, new Response);
    $this->collector->__invoke($event);

    $livewireData = Context::get('livewire');
    expect($livewireData)->toBeArray();
    expect($livewireData['components'][0]['name'])->toBe('counter');
});

it('skips non-livewire requests', function () {
    $request = Request::create('/api/users', 'GET');
    app()->instance('request', $request);

    $event = new RequestHandled($request, new Response);
    $this->collector->__invoke($event);

    expect(Context::get('livewire'))->toBeNull();
});

it('stores originating page from snapshot memo', function () {
    $request = Request::create('/livewire/update', 'POST');
    $request->headers->set('X-Livewire', 'true');
    $request->headers->set('Content-Type', 'application/json');

    $payload = [
        'components' => [
            [
                'snapshot' => json_encode([
                    'memo' => [
                        'name' => 'dashboard-widget',
                        'id' => 'abc123',
                        'path' => '/dashboard/settings',
                    ],
                ]),
            ],
        ],
    ];

    $request->merge($payload);
    app()->instance('request', $request);

    $event = new RequestHandled($request, new Response);
    $this->collector->__invoke($event);

    expect(Context::get('livewire_originating_page'))->toBe('/dashboard/settings');

    $livewireData = Context::get('livewire');
    expect($livewireData['originating_page'])->toBe('/dashboard/settings');
});

it('falls back to referer header for originating page', function () {
    $request = Request::create('/livewire/update', 'POST');
    $request->headers->set('X-Livewire', 'true');
    $request->headers->set('Content-Type', 'application/json');
    $request->headers->set('referer', 'https://example.com/users?page=2');

    $payload = [
        'components' => [
            [
                'snapshot' => json_encode([
                    'memo' => [
                        'name' => 'user-list',
                        'id' => 'abc123',
                        // No path in memo
                    ],
                ]),
            ],
        ],
    ];

    $request->merge($payload);
    app()->instance('request', $request);

    $event = new RequestHandled($request, new Response);
    $this->collector->__invoke($event);

    expect(Context::get('livewire_originating_page'))->toBe('/users?page=2');
});

it('handles empty component payload gracefully', function () {
    $request = Request::create('/livewire/update', 'POST');
    $request->headers->set('X-Livewire', 'true');
    $request->headers->set('Content-Type', 'application/json');

    $request->merge(['components' => []]);
    app()->instance('request', $request);

    $event = new RequestHandled($request, new Response);
    $this->collector->__invoke($event);

    // Should not add context when no components
    expect(Context::get('livewire'))->toBeNull();
});

it('returns enabled status based on config', function () {
    config(['logging.channels.github.tracing.livewire' => true]);
    config(['github-monolog.tracing.livewire' => null]);
    expect($this->collector->isEnabled())->toBeTrue();

    config(['logging.channels.github.tracing.livewire' => false]);
    config(['github-monolog.tracing.livewire' => false]);
    expect($this->collector->isEnabled())->toBeFalse();
});

it('redacts sensitive data in component updates', function () {
    $request = Request::create('/livewire/update', 'POST');
    $request->headers->set('X-Livewire', 'true');
    $request->headers->set('Content-Type', 'application/json');

    $payload = [
        'components' => [
            [
                'snapshot' => json_encode([
                    'memo' => [
                        'name' => 'login-form',
                        'id' => 'abc123',
                    ],
                ]),
                'updates' => [
                    'email' => 'test@example.com',
                    'password' => 'secret123',
                ],
            ],
        ],
    ];

    $request->merge($payload);
    app()->instance('request', $request);

    $event = new RequestHandled($request, new Response);
    $this->collector->__invoke($event);

    $livewireData = Context::get('livewire');
    // Updates should only contain keys, not values (by design)
    expect($livewireData['components'][0]['updates'])->toBe(['email', 'password']);
});
