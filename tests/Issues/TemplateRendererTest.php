<?php

use Naoray\LaravelGithubMonolog\Issues\StubLoader;
use Naoray\LaravelGithubMonolog\Issues\TemplateRenderer;

beforeEach(function () {
    $this->stubLoader = new StubLoader;
    $this->renderer = resolve(TemplateRenderer::class);
});

test('it renders basic log record', function () {
    $record = createLogRecord('Test message');

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('**Log Level:** ERROR')
        ->toContain('**Message:** Test message');
});

test('it renders title without exception', function () {
    $record = createLogRecord('Test message');

    $title = $this->renderer->renderTitle($record);

    expect($title)->toBe('[ERROR] Test message');
});

test('it renders title with exception', function () {
    $record = createLogRecord('Test message', exception: new RuntimeException('Test exception'));

    $title = $this->renderer->renderTitle($record);

    expect($title)->toContain('[ERROR] RuntimeException', 'Test exception');
});

test('it renders context data', function () {
    $record = createLogRecord('Test message', ['user_id' => 123]);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('## Context')
        ->toContain('"user_id": 123');
});

test('it renders extra data', function () {
    $record = createLogRecord('Test message', extra: ['request_id' => 'abc123']);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('## Extra Data')
        ->toContain('"request_id": "abc123"');
});

test('it renders previous exceptions', function () {
    $previous = new RuntimeException('Previous exception');
    $exception = new RuntimeException('Test exception', previous: $previous);
    $record = createLogRecord('Test message', exception: $exception);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('Previous Exception #1')
        ->toContain('Previous exception')
        ->toContain('[Vendor frames]');
});

test('it handles nested stack traces in previous exceptions correctly', function () {
    $previous = new RuntimeException('Previous exception');
    $exception = new RuntimeException('Test exception', previous: $previous);
    $record = createLogRecord('Test message', exception: $exception);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    // Verify that the main stack trace section is present
    expect($rendered)
        ->toContain('View Complete Stack Trace')
        // Verify that the previous exceptions section is present
        ->toContain('View Previous Exceptions');
});

test('it cleans all empty sections', function () {
    $record = createLogRecord('');

    $rendered = $this->renderer->render(
        template: $this->stubLoader->load('comment'),
        record: $record,
        signature: 'test',
    );

    expect($rendered)
        ->toContain('**Type:** ERROR')
        ->toContain('<!-- Signature: test -->');
});

test('it extracts clean message and stack trace when exception is a string in context', function () {
    // Simulate the scenario where exception is logged as a string
    $exceptionString = 'The calculation amount [123.45] does not match the expected total [456.78]. in /path/to/app/Calculations/Calculator.php:49
Stack trace:
#0 /path/to/app/Services/PaymentService.php(83): App\\Calculations\\Calculator->calculate()
#1 /vendor/framework/src/Services/TransactionService.php(247): App\\Services\\PaymentService->process()';

    $record = createLogRecord(
        message: $exceptionString, // The full exception string is in the message
        context: ['exception' => $exceptionString], // Also in context as string
    );

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    // Should have clean short message (not the full exception string)
    // Extract the message line to check it specifically
    preg_match('/\*\*Message:\*\* (.+?)(?:\n|$)/', $rendered, $messageMatches);
    $messageValue = $messageMatches[1] ?? '';

    expect($messageValue)
        ->toBe('The calculation amount [123.45] does not match the expected total [456.78].')
        ->not->toContain('Stack trace:')
        ->not->toContain('#0 /path/to/app/Services/PaymentService.php');

    // Should have class populated (even if generic)
    expect($rendered)
        ->toContain('**Class:**')
        ->toMatch('/\*\*Class:\*\* .+/'); // Class should have a value

    // Should have stack traces populated
    expect($rendered)
        ->toContain('## Stack Trace')
        ->toContain('[stacktrace]')
        ->toContain('App\\Calculations\\Calculator->calculate()');
});
