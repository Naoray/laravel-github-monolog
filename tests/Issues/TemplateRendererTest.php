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
