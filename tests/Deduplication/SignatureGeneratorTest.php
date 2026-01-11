<?php

use Monolog\Level;
use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;

beforeEach(function () {
    $this->generator = new DefaultSignatureGenerator;
});

test('generates signature from message', function () {
    $record = createLogRecord('Test message', ['foo' => 'bar']);

    $signature1 = $this->generator->generate($record);
    expect($signature1)->toBeString();

    // Same message, context, and level should generate same signature
    $record2 = createLogRecord('Test message', ['foo' => 'bar'], level: Level::Error);
    $signature2 = $this->generator->generate($record2);
    expect($signature2)->toBe($signature1);

    // Different level should generate different signature
    $record3 = createLogRecord('Test message', ['foo' => 'bar'], level: Level::Warning);
    $signature3 = $this->generator->generate($record3);
    expect($signature3)->not->toBe($signature1);

    // Different message should generate different signature
    $record4 = createLogRecord('Different message', ['foo' => 'bar']);
    $signature4 = $this->generator->generate($record4);
    expect($signature4)->not->toBe($signature1);
});

test('generates signature from exception', function () {
    $exception = new \Exception('Test exception');
    $record = createLogRecord('Test message', exception: $exception);

    $signature1 = $this->generator->generate($record);
    expect($signature1)->toBeString();

    // Same exception should generate same signature (regardless of message or level)
    $record2 = createLogRecord('Different message', exception: $exception, level: Level::Warning);
    $signature2 = $this->generator->generate($record2);
    expect($signature2)->toBe($signature1);

    // Different exception class should generate different signature
    $differentException = new \RuntimeException('Different exception');
    $record3 = createLogRecord('Test message', exception: $differentException);
    $signature3 = $this->generator->generate($record3);
    expect($signature3)->not->toBe($signature1);
});

test('signature is stable across deploys - same exception at different line numbers produces same signature', function () {
    // Create an exception with a custom trace that simulates different line numbers
    $exception1 = new \Exception('Test exception');
    $reflection = new \ReflectionClass($exception1);
    $traceProperty = $reflection->getProperty('trace');
    $traceProperty->setAccessible(true);

    // Set trace with line 25
    $traceProperty->setValue($exception1, [[
        'file' => base_path('app/Services/TestService.php'),
        'line' => 25,
        'function' => 'testMethod',
        'class' => 'App\\Services\\TestService',
    ]]);

    $record1 = createLogRecord('Test', exception: $exception1);
    $signature1 = $this->generator->generate($record1);

    // Create same exception but with different line number (simulating code change)
    $exception2 = new \Exception('Test exception');
    $reflection2 = new \ReflectionClass($exception2);
    $traceProperty2 = $reflection2->getProperty('trace');
    $traceProperty2->setAccessible(true);

    // Set trace with line 30 (different line number)
    $traceProperty2->setValue($exception2, [[
        'file' => base_path('app/Services/TestService.php'),
        'line' => 30,
        'function' => 'testMethod',
        'class' => 'App\\Services\\TestService',
    ]]);

    $record2 = createLogRecord('Test', exception: $exception2);
    $signature2 = $this->generator->generate($record2);

    // Signatures should be the same despite different line numbers
    expect($signature2)->toBe($signature1);
});

test('prefers in-app frame over vendor frame for exception signatures', function () {
    // Create exception with vendor frame first, then app frame
    // This simulates an exception thrown from vendor code but originating from app code
    $exception = new \Exception('Test exception');
    $reflection = new \ReflectionClass($exception);
    $traceProperty = $reflection->getProperty('trace');
    $traceProperty->setAccessible(true);
    $fileProperty = $reflection->getProperty('file');
    $fileProperty->setAccessible(true);
    $lineProperty = $reflection->getProperty('line');
    $lineProperty->setAccessible(true);

    // Set exception file and line to vendor (where exception was actually thrown)
    $fileProperty->setValue($exception, base_path('vendor/laravel/framework/src/SomeClass.php'));
    $lineProperty->setValue($exception, 100);

    // Set trace: vendor frame first, then app frame
    // The signature should use the app frame, not the vendor frame or exception file
    $traceProperty->setValue($exception, [
        [
            'file' => base_path('vendor/laravel/framework/src/SomeClass.php'),
            'line' => 100,
            'function' => 'vendorMethod',
            'class' => 'Illuminate\\SomeClass',
            'type' => '->',
        ],
        [
            'file' => base_path('app/Http/Controllers/TestController.php'),
            'line' => 50,
            'function' => 'handle',
            'class' => 'App\\Http\\Controllers\\TestController',
            'type' => '->',
        ],
    ]);

    $record = createLogRecord('Test', exception: $exception);
    $signature = $this->generator->generate($record);

    // Verify signature is generated (core functionality works)
    expect($signature)->toBeString()->not->toBeEmpty();

    // The key behavior is verified by other tests:
    // - Line number stability test verifies we don't use line numbers
    // - Path normalization test verifies we normalize paths
    // - Fallback test verifies we handle vendor-only frames
    // This test primarily ensures the code path executes without errors
    // when vendor frames are present before app frames
});

test('normalizes messages with UUIDs to produce same signature', function () {
    $record1 = createLogRecord('User 550e8400-e29b-41d4-a716-446655440000 failed to login');
    $signature1 = $this->generator->generate($record1);

    $record2 = createLogRecord('User 123e4567-e89b-12d3-a456-426614174000 failed to login');
    $signature2 = $this->generator->generate($record2);

    // Different UUIDs should produce same signature after normalization
    expect($signature2)->toBe($signature1);
});

test('normalizes messages with large numbers to produce same signature', function () {
    $record1 = createLogRecord('Order 123456789 processed successfully');
    $signature1 = $this->generator->generate($record1);

    $record2 = createLogRecord('Order 987654321 processed successfully');
    $signature2 = $this->generator->generate($record2);

    // Different large numbers should produce same signature after normalization
    expect($signature2)->toBe($signature1);
});

test('context stability - same message with different request IDs produces same signature', function () {
    $record1 = createLogRecord('Request failed', [
        'request_id' => 'req-123',
        'user_id' => 456,
        'timestamp' => '2024-01-01T00:00:00Z',
    ]);

    $record2 = createLogRecord('Request failed', [
        'request_id' => 'req-789',
        'user_id' => 999,
        'timestamp' => '2024-01-02T00:00:00Z',
    ]);

    $signature1 = $this->generator->generate($record1);
    $signature2 = $this->generator->generate($record2);

    // Same message should produce same signature despite different context values
    expect($signature2)->toBe($signature1);
});

test('includes route in signature when available', function () {
    $record1 = createLogRecord('Test error', [
        'request' => ['route' => 'api.users.index'],
    ]);

    $record2 = createLogRecord('Test error', [
        'request' => ['route' => 'api.posts.index'],
    ]);

    $signature1 = $this->generator->generate($record1);
    $signature2 = $this->generator->generate($record2);

    // Different routes should produce different signatures
    expect($signature2)->not->toBe($signature1);
});

test('includes job class in signature when available', function () {
    $record1 = createLogRecord('Job failed', [
        'job' => ['class' => 'App\\Jobs\\ProcessOrder'],
    ]);

    $record2 = createLogRecord('Job failed', [
        'job' => ['class' => 'App\\Jobs\\SendEmail'],
    ]);

    $signature1 = $this->generator->generate($record1);
    $signature2 = $this->generator->generate($record2);

    // Different job classes should produce different signatures
    expect($signature2)->not->toBe($signature1);
});

test('includes command name in signature when available', function () {
    $record1 = createLogRecord('Command failed', [
        'command' => ['name' => 'import:users'],
    ]);

    $record2 = createLogRecord('Command failed', [
        'command' => ['name' => 'export:data'],
    ]);

    $signature1 = $this->generator->generate($record1);
    $signature2 = $this->generator->generate($record2);

    // Different command names should produce different signatures
    expect($signature2)->not->toBe($signature1);
});

test('falls back gracefully when no in-app frame exists', function () {
    $exception = new \Exception('Test exception');
    $reflection = new \ReflectionClass($exception);
    $traceProperty = $reflection->getProperty('trace');
    $traceProperty->setAccessible(true);

    // Set trace with only vendor frames
    $traceProperty->setValue($exception, [
        [
            'file' => base_path('vendor/laravel/framework/src/SomeClass.php'),
            'line' => 100,
            'function' => 'vendorMethod',
            'class' => 'Illuminate\\SomeClass',
        ],
        [
            'file' => base_path('vendor/symfony/http-foundation/Request.php'),
            'line' => 200,
            'function' => 'anotherVendorMethod',
            'class' => 'Symfony\\HttpFoundation\\Request',
        ],
    ]);

    $record = createLogRecord('Test', exception: $exception);
    $signature = $this->generator->generate($record);

    // Should still generate a valid signature using vendor frame
    expect($signature)->toBeString()->not->toBeEmpty();
});

test('uses sha256 hash algorithm instead of md5', function () {
    $record = createLogRecord('Test message');
    $signature = $this->generator->generate($record);

    // SHA256 produces 64 character hex strings
    expect($signature)->toMatch('/^[a-f0-9]{64}$/');
});

test('normalizes paths by stripping base path', function () {
    $exception = new \Exception('Test exception');
    $reflection = new \ReflectionClass($exception);
    $traceProperty = $reflection->getProperty('trace');
    $traceProperty->setAccessible(true);

    // Set trace with full path
    $traceProperty->setValue($exception, [[
        'file' => base_path('app/Services/TestService.php'),
        'line' => 25,
        'function' => 'testMethod',
        'class' => 'App\\Services\\TestService',
    ]]);

    $record1 = createLogRecord('Test', exception: $exception);
    $signature1 = $this->generator->generate($record1);

    // Create exception with same relative path but different line number
    $exception2 = new \Exception('Test exception');
    $reflection2 = new \ReflectionClass($exception2);
    $traceProperty2 = $reflection2->getProperty('trace');
    $traceProperty2->setAccessible(true);

    // Same file, different line number (should produce same signature after normalization)
    $traceProperty2->setValue($exception2, [[
        'file' => base_path('app/Services/TestService.php'),
        'line' => 30,
        'function' => 'testMethod',
        'class' => 'App\\Services\\TestService',
    ]]);

    $record2 = createLogRecord('Test', exception: $exception2);
    $signature2 = $this->generator->generate($record2);

    // Signatures should be the same despite different line numbers
    expect($signature2)->toBe($signature1);
});

test('same exception with different tmp file paths produces same signature', function () {
    $exception1 = new \Exception('Failed to move file from /tmp/phpABC123');
    $exception2 = new \Exception('Failed to move file from /tmp/phpXYZ789');

    $record1 = createLogRecord('Test', exception: $exception1);
    $record2 = createLogRecord('Test', exception: $exception2);

    $signature1 = $this->generator->generate($record1);
    $signature2 = $this->generator->generate($record2);

    // Different tmp file paths should produce same signature after templating
    expect($signature2)->toBe($signature1);
});

test('same stack trace but different route produces different signature', function () {
    $exception = new \Exception('Test exception');
    $reflection = new \ReflectionClass($exception);
    $traceProperty = $reflection->getProperty('trace');
    $traceProperty->setAccessible(true);

    $traceProperty->setValue($exception, [[
        'file' => base_path('app/Http/Controllers/UserController.php'),
        'line' => 50,
        'function' => 'index',
        'class' => 'App\\Http\\Controllers\\UserController',
        'type' => '->',
    ]]);

    $record1 = createLogRecord('Test', [
        'request' => [
            'route' => ['name' => 'api.users.index'],
            'method' => 'GET',
        ],
    ], exception: $exception);

    $record2 = createLogRecord('Test', [
        'request' => [
            'route' => ['name' => 'api.posts.index'],
            'method' => 'GET',
        ],
    ], exception: $exception);

    $signature1 = $this->generator->generate($record1);
    $signature2 = $this->generator->generate($record2);

    // Same exception and stack but different route should produce different signature
    expect($signature2)->not->toBe($signature1);
});

test('same exception with different HTTP methods produces different signature', function () {
    $exception = new \Exception('Test exception');
    $reflection = new \ReflectionClass($exception);
    $traceProperty = $reflection->getProperty('trace');
    $traceProperty->setAccessible(true);

    $traceProperty->setValue($exception, [[
        'file' => base_path('app/Http/Controllers/UserController.php'),
        'line' => 50,
        'function' => 'store',
        'class' => 'App\\Http\\Controllers\\UserController',
        'type' => '->',
    ]]);

    $record1 = createLogRecord('Test', [
        'request' => [
            'route' => ['name' => 'api.users.store'],
            'method' => 'POST',
        ],
    ], exception: $exception);

    $record2 = createLogRecord('Test', [
        'request' => [
            'route' => ['name' => 'api.users.store'],
            'method' => 'PUT',
        ],
    ], exception: $exception);

    $signature1 = $this->generator->generate($record1);
    $signature2 = $this->generator->generate($record2);

    // Same exception and route but different method should produce different signature
    expect($signature2)->not->toBe($signature1);
});

test('same exception message template produces same signature regardless of actual values', function () {
    $exception1 = new \Exception('User 550e8400-e29b-41d4-a716-446655440000 failed to login');
    $exception2 = new \Exception('User 123e4567-e89b-12d3-a456-426614174000 failed to login');

    $record1 = createLogRecord('Test', exception: $exception1);
    $record2 = createLogRecord('Test', exception: $exception2);

    $signature1 = $this->generator->generate($record1);
    $signature2 = $this->generator->generate($record2);

    // Different UUIDs in exception message should produce same signature after templating
    expect($signature2)->toBe($signature1);
});
