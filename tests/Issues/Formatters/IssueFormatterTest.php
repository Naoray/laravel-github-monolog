<?php

use Naoray\LaravelGithubMonolog\Issues\Formatters\Formatted;
use Naoray\LaravelGithubMonolog\Issues\Formatters\IssueFormatter;

beforeEach(function () {
    $this->formatter = app()->make(IssueFormatter::class);
});

test('it formats basic log records', function () {
    $record = createLogRecord('Test error message', signature: 'test-signature');

    $formatted = $this->formatter->format($record);

    expect($formatted)
        ->toBeInstanceOf(Formatted::class)
        ->and($formatted->title)->toContain('[ERROR] Test error message')
        ->and($formatted->body)->toContain('**Log Level:** ERROR')
        ->and($formatted->body)->toContain('Test error message');
});

test('it formats exceptions with file and line information', function () {
    $record = createLogRecord('Error occurred', exception: new RuntimeException('Test exception'), signature: 'test-signature');

    $formatted = $this->formatter->format($record);

    expect($formatted->title)
        ->toContain('RuntimeException')
        ->toContain('.php:')
        ->and($formatted->body)
        ->toContain('Test exception')
        ->toContain('Stack Trace');
});

test('it truncates long titles', function () {
    $longMessage = str_repeat('a', 90);
    $record = createLogRecord($longMessage, signature: 'test-signature');

    $formatted = $this->formatter->format($record);

    expect(mb_strlen($formatted->title))->toBeLessThanOrEqual(100);
});

test('it includes context data in formatted output', function () {
    $record = createLogRecord(
        'Test message',
        exception: new RuntimeException('Test exception'),
        context: [
            'user_id' => 123,
            'action' => 'login',
        ],
        signature: 'test-signature',
    );

    $formatted = $this->formatter->format($record);

    expect($formatted->body)
        ->toContain('"user_id": 123')
        ->toContain('"action": "login"');
});

test('it formats stack traces with collapsible vendor frames', function () {
    $exception = new Exception('Test exception');
    $reflection = new ReflectionClass($exception);
    $traceProperty = $reflection->getProperty('trace');
    $traceProperty->setAccessible(true);

    // Set a custom stack trace with both vendor and application frames
    $traceProperty->setValue($exception, [
        [
            'file' => base_path('app/Http/Controllers/TestController.php'),
            'line' => 25,
            'function' => 'testMethod',
            'class' => 'TestController',
        ],
        [
            'file' => base_path('vendor/laravel/framework/src/Testing.php'),
            'line' => 50,
            'function' => 'vendorMethod',
            'class' => 'VendorClass',
        ],
        [
            'file' => base_path('vendor/another/package/src/File.php'),
            'line' => 100,
            'function' => 'anotherVendorMethod',
            'class' => 'AnotherVendorClass',
        ],
        [
            'file' => base_path('app/Services/TestService.php'),
            'line' => 30,
            'function' => 'serviceMethod',
            'class' => 'TestService',
        ],
    ]);

    $record = createLogRecord('Error occurred', exception: $exception, signature: 'test-signature');

    $formatted = $this->formatter->format($record);

    expect($formatted->body)
        ->toContain('app/Http/Controllers/TestController.php')
        ->toContain('app/Services/TestService.php')
        ->toContain('[Vendor frames]');
});
