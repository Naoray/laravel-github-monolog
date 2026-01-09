<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Issues\StubLoader;
use Naoray\LaravelGithubMonolog\Issues\TemplateRenderer;
use Naoray\LaravelGithubMonolog\Tracing\ContextProcessor;

beforeEach(function () {
    $this->stubLoader = new StubLoader;
    $this->renderer = resolve(TemplateRenderer::class);
    $this->processor = new ContextProcessor;
    Context::flush();
});

afterEach(function () {
    Context::flush();
});

it('includes context data in context section not extra', function () {
    // Arrange - Add context data that is NOT excluded from context section
    // (user, request, route, etc. are excluded and go to their own sections)
    Context::add('custom_key', 'custom_value');
    Context::add('another_key', ['nested' => 'data']);

    $record = createLogRecord('Test message');
    $record = ($this->processor)($record);

    // Verify context data is in the record after processing
    expect($record->context)
        ->toHaveKey('custom_key')
        ->toHaveKey('another_key');

    // Act
    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    // Assert - Context section should exist and contain the data
    // Note: The section cleaner removes empty sections, so if context exists, the section should be present
    expect($rendered)->toContain('<summary>📦 Context</summary>');

    // Extract context section (it might be cleaned if empty, so check if it exists first)
    if (preg_match('/<!-- context:start -->(.*?)<!-- context:end -->/s', $rendered, $contextMatches)) {
        $contextSection = $contextMatches[1];
        expect($contextSection)
            ->toContain('"custom_key"')
            ->toContain('"another_key"')
            ->toContain('"custom_value"')
            ->toContain('"nested"');
    } else {
        // If section doesn't exist, it was cleaned because it was empty - this should not happen
        expect($rendered)->toContain('"custom_key"')->toContain('"another_key"');
    }

    // Extract extra section to verify context data is NOT there
    preg_match('/<!-- extra:start -->(.*?)<!-- extra:end -->/s', $rendered, $extraMatches);
    $extraSection = $extraMatches[1] ?? '';

    if (! empty($extraSection)) {
        expect($extraSection)
            ->not->toContain('"custom_key"')
            ->not->toContain('"another_key"');
    }
});

it('does not include context data in extra section', function () {
    // Arrange
    Context::add('user', ['id' => 456]);

    $record = createLogRecord('Test message', extra: ['custom_extra' => 'value'], signature: 'test-sig');

    // Verify context data is added after processing
    expect($record->context)->not->toHaveKey('user');
    $record = ($this->processor)($record);
    expect($record->context)->toHaveKey('user');

    // Verify context for formatContext
    $contextForFormat = Arr::except($record->context, ['exception']);
    expect($contextForFormat)->not->toBeEmpty()->toHaveKey('user');

    // Act
    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record, 'test-sig');

    // Assert - Extract the extra section (if it exists)
    preg_match('/<!-- extra:start -->(.*?)<!-- extra:end -->/s', $rendered, $extraMatches);
    $extraSection = $extraMatches[1] ?? '';

    if (! empty($extraSection)) {
        expect($extraSection)
            ->toContain('"custom_extra": "value"')
            ->not->toContain('"user"')
            ->not->toContain('"id": 456');
    }

    // Verify user data IS in User section (not context section, as it's now in its own section)
    expect($rendered)->toContain('<summary>👤 User Details</summary>');
    // User data should be present in the rendered output
    expect($rendered)->toContain('456');
});
