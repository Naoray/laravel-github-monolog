<?php

use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Formatters\GithubIssueFormatted;
use Naoray\LaravelGithubMonolog\Formatters\GithubIssueFormatter;

test('it formats basic log records', function () {
    $formatter = new GithubIssueFormatter;
    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: 'Test error message',
        context: [],
        extra: []
    );

    $formatted = $formatter->format($record);

    expect($formatted)
        ->toBeInstanceOf(GithubIssueFormatted::class)
        ->and($formatted->title)->toContain('[ERROR] Test error message')
        ->and($formatted->body)->toContain('**Log Level:** ERROR')
        ->and($formatted->body)->toContain('Test error message');
});

test('it formats exceptions with file and line information', function () {
    $formatter = new GithubIssueFormatter;
    $exception = new RuntimeException('Test exception');
    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: 'Error occurred',
        context: ['exception' => $exception],
        extra: []
    );

    $formatted = $formatter->format($record);

    expect($formatted->title)
        ->toContain('RuntimeException')
        ->toContain('.php:')
        ->and($formatted->body)
        ->toContain('Test exception')
        ->toContain('Stack Trace:');
});

test('it truncates long titles', function () {
    $formatter = new GithubIssueFormatter;
    $longMessage = str_repeat('a', 90);
    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: $longMessage,
        context: [],
        extra: []
    );

    $formatted = $formatter->format($record);

    expect(mb_strlen($formatted->title))->toBeLessThanOrEqual(100);
});

test('it includes context data in formatted output', function () {
    $formatter = new GithubIssueFormatter;
    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: 'Test message',
        context: ['user_id' => 123, 'action' => 'login', 'exception' => new RuntimeException('Test exception')],
        extra: []
    );

    $formatted = $formatter->format($record);

    expect($formatted->body)
        ->toContain('"user_id": 123')
        ->toContain('"action": "login"');
});

test('it generates consistent signatures for similar errors', function () {
    $formatter = new GithubIssueFormatter;

    // Create a single exception and format it twice
    $exception = new RuntimeException('Test exception');
    $datetime = new DateTimeImmutable;
    $record = new LogRecord(
        datetime: $datetime,
        channel: 'test',
        level: Level::Error,
        message: 'Error',
        context: ['exception' => $exception],
        extra: []
    );

    $formatted1 = $formatter->format($record);
    $formatted2 = $formatter->format($record); // Format the exact same record

    // The signatures should be identical when formatting the same record
    expect($formatted1->signature)->toBe($formatted2->signature);
});

test('it generates different signatures for different errors', function () {
    $formatter = new GithubIssueFormatter;

    $exception1 = new RuntimeException('First error');
    $exception2 = new RuntimeException('Different error');
    $datetime = new DateTimeImmutable;

    $record1 = new LogRecord(
        datetime: $datetime,
        channel: 'test',
        level: Level::Error,
        message: 'Error',
        context: ['exception' => $exception1],
        extra: []
    );

    $record2 = new LogRecord(
        datetime: $datetime,
        channel: 'test',
        level: Level::Error,
        message: 'Error',
        context: ['exception' => $exception2],
        extra: []
    );

    $formatted1 = $formatter->format($record1);
    $formatted2 = $formatter->format($record2);

    // Different errors should have different signatures
    expect($formatted1->signature)->not->toBe($formatted2->signature);
});

test('it formats stack traces with collapsible vendor frames', function () {
    $formatter = new GithubIssueFormatter;

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

    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: 'Error occurred',
        context: ['exception' => $exception],
        extra: []
    );

    $formatted = $formatter->format($record);

    // Verify that app frames are directly visible
    expect($formatted->body)
        ->toContain('app/Http/Controllers/TestController.php')
        ->toContain('app/Services/TestService.php')
        // Verify that vendor frames are wrapped in details tags
        ->toContain('[Vendor frames]')
        ->toContain('vendor/laravel/framework/src/Testing.php')
        ->toContain('vendor/another/package/src/File.php');
});
