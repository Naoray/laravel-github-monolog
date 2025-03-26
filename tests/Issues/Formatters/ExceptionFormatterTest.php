<?php

use Naoray\LaravelGithubMonolog\Issues\Formatters\ExceptionFormatter;
use Naoray\LaravelGithubMonolog\Issues\Formatters\StackTraceFormatter;

beforeEach(function () {
    $this->formatter = resolve(ExceptionFormatter::class);
});

test('it formats exception details', function () {
    $exception = new RuntimeException('Test exception');
    $record = createLogRecord('Test message', exception: $exception);

    $result = $this->formatter->format($record);

    // Pad the stack trace lines to not mess up the test assertions
    $exceptionTrace = collect(explode("\n", $exception->getTraceAsString()))
        ->map(fn ($line) => (new StackTraceFormatter)->padStackTraceLine($line))
        ->join("\n");

    expect($result)
        ->toBeArray()
        ->toHaveKeys(['message', 'simplified_stack_trace', 'full_stack_trace'])
        ->and($result['message'])->toBe('Test exception')
        ->and($result['simplified_stack_trace'])->toContain('[Vendor frames]')
        ->and($result['full_stack_trace'])->toContain($exceptionTrace);
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

test('it properly formats exception with stack trace in message', function () {
    // Create a custom exception class that mimics our problematic behavior
    $exception = new class('Error message') extends Exception {
        public function __construct()
        {
            parent::__construct("The calculation amount [123.45] does not match the expected total [456.78]. in /path/to/app/Calculations/Calculator.php:49
Stack trace:
#0 /path/to/app/Services/PaymentService.php(83): App\\Calculations\\Calculator->calculate()
#1 /vendor/framework/src/Services/TransactionService.php(247): App\\Services\\PaymentService->process()");
        }
    };

    $record = createLogRecord('Test message', exception: $exception);

    $result = $this->formatter->format($record);

    // The formatter should extract just the actual error message without the file path or stack trace
    expect($result)
        ->toBeArray()
        ->toHaveKeys(['message', 'simplified_stack_trace', 'full_stack_trace'])
        ->and($result['message'])->toBe('The calculation amount [123.45] does not match the expected total [456.78].')
        ->and($result['simplified_stack_trace'])->toContain('[stacktrace]')
        ->and($result['simplified_stack_trace'])->toContain('App\\Calculations\\Calculator->calculate()');
});

test('it handles exceptions with string in context', function () {
    // Create a generic exception string
    $exceptionString = "The calculation amount [123.45] does not match the expected total [456.78]. in /path/to/app/Calculations/Calculator.php:49
Stack trace:
#0 /path/to/app/Services/PaymentService.php(83): App\\Calculations\\Calculator->calculate()
#1 /vendor/framework/src/Services/TransactionService.php(247): App\\Services\\PaymentService->process()";

    // Create a record with a string in the exception context
    $record = createLogRecord('Test message', ['exception' => $exceptionString]);

    $result = $this->formatter->format($record);

    // Should extract just the clean message
    expect($result)
        ->toBeArray()
        ->toHaveKeys(['message', 'simplified_stack_trace', 'full_stack_trace'])
        ->and($result['message'])->toBe('The calculation amount [123.45] does not match the expected total [456.78].')
        ->and($result['simplified_stack_trace'])->toContain('[stacktrace]')
        ->and($result['simplified_stack_trace'])->toContain('App\\Calculations\\Calculator->calculate()');
});
