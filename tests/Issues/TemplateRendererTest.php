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
        ->toContain('**Level:** ERROR')
        ->toContain('**Message:** Test message')
        ->toContain('## Triage Information');
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
        ->toContain('<summary>📦 Context</summary>')
        ->toContain('"user_id": 123');
});

test('it renders extra data', function () {
    $record = createLogRecord('Test message', extra: ['request_id' => 'abc123']);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('<summary>➕ Extra Data</summary>')
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

    // Should have exception class populated (even if generic)
    expect($rendered)
        ->toContain('**Exception:**')
        ->toMatch('/\*\*Exception:\*\* .+/'); // Exception should have a value

    // Should have stack traces populated
    expect($rendered)
        ->toContain('<summary>📋 Stack Trace</summary>')
        ->toContain('[stacktrace]')
        ->toContain('App\\Calculations\\Calculator->calculate()');
});

test('it formats timestamp placeholder correctly', function () {
    $record = createLogRecord('Test message');

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('**Timestamp:**')
        ->toMatch('/\*\*Timestamp:\*\* \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/');
});

test('it formats route summary as method and path', function () {
    $record = createLogRecord('Test message', [
        'request' => [
            'method' => 'POST',
            'url' => 'https://example.com/api/users',
        ],
    ]);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('**Route:** POST /api/users');
});

test('it returns empty string for route summary when request data is missing', function () {
    $record = createLogRecord('Test message');

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('**Route:**');
});

test('it formats user summary with user id', function () {
    $record = createLogRecord('Test message', [
        'user' => [
            'id' => 123,
        ],
    ]);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('**User:** User ID: 123');
});

test('it formats user summary as unauthenticated when user data is missing', function () {
    $record = createLogRecord('Test message');

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('**User:** Unauthenticated');
});

test('it formats user summary as unauthenticated when user id is missing', function () {
    $record = createLogRecord('Test message', [
        'user' => [
            'name' => 'John Doe',
        ],
    ]);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('**User:** Unauthenticated');
});

test('it extracts environment name from environment context', function () {
    $record = createLogRecord('Test message', [
        'environment' => [
            'APP_ENV' => 'production',
        ],
    ]);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('**Environment:** production');
});

test('it extracts environment name from lowercase app_env key', function () {
    $record = createLogRecord('Test message', [
        'environment' => [
            'app_env' => 'staging',
        ],
    ]);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('**Environment:** staging');
});

test('it returns empty string for environment name when not available', function () {
    $record = createLogRecord('Test message');

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('**Environment:**');
});

test('issue template renders triage header with all fields', function () {
    $record = createLogRecord('Test message', [
        'request' => [
            'method' => 'GET',
            'url' => 'https://example.com/test',
            'headers' => ['X-Request-ID' => 'test123'],
        ],
        'user' => ['id' => 456],
        'environment' => ['APP_ENV' => 'testing'],
    ]);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record, 'sig123');

    expect($rendered)
        ->toContain('## Triage Information')
        ->toContain('**Level:** ERROR')
        ->toContain('**Exception:**')
        ->toContain('**Signature:** sig123')
        ->toContain('**Timestamp:**')
        ->toContain('**Environment:** testing')
        ->toContain('**Route:** GET /test')
        ->toContain('**User:** User ID: 456')
        ->toContain('**Message:** Test message');
});

test('issue template wraps verbose sections in details blocks', function () {
    $record = createLogRecord('Test message', [
        'environment' => ['APP_ENV' => 'testing'],
        'request' => ['method' => 'GET', 'url' => 'https://example.com'],
        'user' => ['id' => 123],
        'custom_context' => 'test',
    ], extra: ['extra_key' => 'extra_value']);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('<details>')
        ->toContain('<summary>🌍 Environment</summary>')
        ->toContain('<summary>📥 Request</summary>')
        ->toContain('<summary>👤 User Details</summary>')
        ->toContain('<summary>📦 Context</summary>')
        ->toContain('<summary>➕ Extra Data</summary>');
});

test('comment template always includes request section', function () {
    $record = createLogRecord('Test message', [
        'request' => [
            'method' => 'POST',
            'url' => 'https://example.com/api',
            'headers' => ['X-Request-ID' => 'req456'],
        ],
    ]);

    $rendered = $this->renderer->render($this->stubLoader->load('comment'), $record);

    expect($rendered)
        ->toContain('<summary>📥 Request</summary>')
        ->toContain('**Route:** POST /api');
});

test('comment template does not include environment section', function () {
    $record = createLogRecord('Test message', [
        'environment' => ['APP_ENV' => 'production'],
    ]);

    $rendered = $this->renderer->render($this->stubLoader->load('comment'), $record);

    expect($rendered)
        ->not->toContain('<summary>🌍 Environment</summary>')
        ->not->toContain('<!-- environment:start -->');
});

test('triage header renders correctly with missing optional data', function () {
    $record = createLogRecord('Test message');

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record, 'sig789');

    expect($rendered)
        ->toContain('## Triage Information')
        ->toContain('**Level:** ERROR')
        ->toContain('**Signature:** sig789')
        ->toContain('**Timestamp:**')
        ->toContain('**Environment:**')
        ->toContain('**Route:**')
        ->toContain('**User:** Unauthenticated')
        ->toContain('**Message:** Test message');
});
