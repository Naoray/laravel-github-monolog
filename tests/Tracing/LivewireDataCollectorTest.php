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
    expect($livewireData['components'][0]['methods'])->toBe([
        ['method' => 'save', 'params' => []],
    ]);
    expect($livewireData['components'][0]['updates'])->toBe(['name' => 'John Doe']);
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
    // Updates now include values, with sensitive fields redacted
    expect($livewireData['components'][0]['updates']['email'])->toBe('test@example.com');
    expect($livewireData['components'][0]['updates']['password'])->toBe('[9 bytes redacted]');
});

it('captures method call parameters', function () {
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
                    ],
                ]),
                'calls' => [
                    ['method' => 'save', 'params' => ['draft' => true]],
                    ['method' => 'validate', 'params' => ['name', 'email']],
                ],
            ],
        ],
    ];

    $request->merge($payload);
    app()->instance('request', $request);

    $event = new RequestHandled($request, new Response);
    $this->collector->__invoke($event);

    $livewireData = Context::get('livewire');
    expect($livewireData['components'][0]['methods'])->toBe([
        ['method' => 'save', 'params' => ['draft' => true]],
        ['method' => 'validate', 'params' => ['name', 'email']],
    ]);
});

it('captures update values alongside keys', function () {
    $request = Request::create('/livewire/update', 'POST');
    $request->headers->set('X-Livewire', 'true');
    $request->headers->set('Content-Type', 'application/json');

    $payload = [
        'components' => [
            [
                'snapshot' => json_encode([
                    'memo' => [
                        'name' => 'contact-form',
                        'id' => 'def456',
                    ],
                ]),
                'updates' => [
                    'name' => 'Jane Smith',
                    'email' => 'jane@example.com',
                    'message' => 'Hello there',
                ],
            ],
        ],
    ];

    $request->merge($payload);
    app()->instance('request', $request);

    $event = new RequestHandled($request, new Response);
    $this->collector->__invoke($event);

    $livewireData = Context::get('livewire');
    expect($livewireData['components'][0]['updates'])->toBe([
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
        'message' => 'Hello there',
    ]);
});

it('redacts sensitive data in method params', function () {
    $request = Request::create('/livewire/update', 'POST');
    $request->headers->set('X-Livewire', 'true');
    $request->headers->set('Content-Type', 'application/json');

    $payload = [
        'components' => [
            [
                'snapshot' => json_encode([
                    'memo' => [
                        'name' => 'auth-form',
                        'id' => 'auth123',
                    ],
                ]),
                'calls' => [
                    ['method' => 'login', 'params' => ['password' => 'my-secret-pw']],
                ],
            ],
        ],
    ];

    $request->merge($payload);
    app()->instance('request', $request);

    $event = new RequestHandled($request, new Response);
    $this->collector->__invoke($event);

    $livewireData = Context::get('livewire');
    expect($livewireData['components'][0]['methods'][0]['method'])->toBe('login');
    expect($livewireData['components'][0]['methods'][0]['params']['password'])->toBe('[12 bytes redacted]');
});

it('defaults to empty params array when calls have no params key', function () {
    $request = Request::create('/livewire/update', 'POST');
    $request->headers->set('X-Livewire', 'true');
    $request->headers->set('Content-Type', 'application/json');

    $payload = [
        'components' => [
            [
                'snapshot' => json_encode([
                    'memo' => [
                        'name' => 'counter',
                        'id' => 'cnt123',
                    ],
                ]),
                'calls' => [
                    ['method' => 'increment'],
                ],
            ],
        ],
    ];

    $request->merge($payload);
    app()->instance('request', $request);

    $event = new RequestHandled($request, new Response);
    $this->collector->__invoke($event);

    $livewireData = Context::get('livewire');
    expect($livewireData['components'][0]['methods'])->toBe([
        ['method' => 'increment', 'params' => []],
    ]);
});
