<?php

use Naoray\LaravelGithubMonolog\Issues\Formatters\ExceptionFormatter;

beforeEach(function () {
    $this->formatter = resolve(ExceptionFormatter::class);
});

test('it formats exception details', function () {
    $exception = new RuntimeException('Test exception');
    $record = createLogRecord('Test message', exception: $exception);

    $result = $this->formatter->format($record);

    expect($result)
        ->toBeArray()
        ->toHaveKeys(['message', 'simplified_stack_trace', 'full_stack_trace'])
        ->and($result['message'])->toBe('Test exception')
        ->and($result['simplified_stack_trace'])->toContain('[Vendor frames]')
        ->and($result['full_stack_trace'])->toContain($exception->getTraceAsString());
});

test('it returns empty array for non-exception records', function () {
    $record = createLogRecord('Test message');

    expect($this->formatter->format($record))->toBeArray()->toBeEmpty();
});

test('it formats exception title', function () {
    $exception = new RuntimeException('Test exception');

    $title = $this->formatter->formatTitle($exception, 'ERROR');

    expect($title)
        ->toContain('[ERROR]')
        ->toContain('RuntimeException')
        ->toContain('Test exception');
});

test('it truncates long exception messages in title', function () {
    $longMessage = str_repeat('a', 150);
    $exception = new RuntimeException($longMessage);

    $title = $this->formatter->formatTitle($exception, 'ERROR');

    // Title format: [ERROR] RuntimeException in /path/to/file.php:123 - {truncated_message}
    // We check that the message part is truncated
    expect($title)
        ->toContain('[ERROR]')
        ->toContain('RuntimeException')
        ->toContain('...');
});
