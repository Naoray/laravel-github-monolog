<?php

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\TestHandler;
use Monolog\Level;
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

it('captures route data when route throws an exception', function () {
    // Arrange - Register a route that throws an exception
    Route::get('/test-route/{id}', function ($id) {
        throw new RuntimeException("Test exception for route with id: {$id}");
    })->name('test.route.show');

    // Create a test handler to capture log records
    $testHandler = new TestHandler(Level::Debug);

    // Get the default logger and add our test handler
    $logger = Log::getLogger();
    $logger->pushHandler($testHandler);

    // Act - Make a request to the route that throws an exception
    try {
        $response = $this->get('/test-route/123');
    } catch (RuntimeException $e) {
        // Exception is expected, manually log it to test the context
        Log::error('Test exception', [
            'exception' => $e,
        ]);
    }

    // Assert - Verify that a log record was created
    expect($testHandler->getRecords())->not->toBeEmpty();

    // Get the error level record
    $errorRecords = array_filter($testHandler->getRecords(), fn($record) => $record['level'] >= Level::Error->value);
    expect($errorRecords)->not->toBeEmpty();

    $logRecord = $errorRecords[array_key_first($errorRecords)];

    // Process the record through ContextProcessor to add context data
    $record = createLogRecord(
        $logRecord['message'],
        $logRecord['context'],
        $logRecord['extra'] ?? [],
        Level::fromValue($logRecord['level']),
        $logRecord['context']['exception'] ?? null
    );

    $processedRecord = ($this->processor)($record);

    // Verify request data is in the context after processing
    expect($processedRecord->context)->toHaveKey('request');
    expect($processedRecord->context['request'])->toHaveKey('url');
    expect($processedRecord->context['request'])->toHaveKey('method');
    expect($processedRecord->context['request'])->toHaveKey('full_url');

    // Verify the request data matches what we expect
    expect($processedRecord->context['request']['method'])->toBe('GET');
    expect($processedRecord->context['request']['url'])->toContain('/test-route/123');

    // Verify it renders correctly in the issue template
    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $processedRecord);

    expect($rendered)
        ->toContain('## Request')
        ->toContain('"method": "GET"')
        ->toContain('/test-route/123');
});

it('captures route data for POST route that throws an exception', function () {
    // Arrange - Register a POST route that throws an exception
    Route::post('/api/posts', function () {
        throw new RuntimeException('Failed to create post');
    })->name('api.posts.store');

    // Create a test handler to capture log records
    $testHandler = new TestHandler(Level::Debug);

    // Get the default logger and add our test handler
    $logger = Log::getLogger();
    $logger->pushHandler($testHandler);

    // Act - Make a POST request to the route that throws an exception
    try {
        $this->post('/api/posts', ['title' => 'Test Post']);
    } catch (RuntimeException $e) {
        // Exception is expected, manually log it to test
        Log::error('Failed to create post', [
            'exception' => $e,
        ]);
    }

    // Assert - Verify that a log record was created with route data
    expect($testHandler->getRecords())->not->toBeEmpty();

    $errorRecords = array_filter($testHandler->getRecords(), fn($record) => $record['level'] >= Level::Error->value);
    expect($errorRecords)->not->toBeEmpty();

    $logRecord = $errorRecords[array_key_first($errorRecords)];

    // Process the record through ContextProcessor
    $record = createLogRecord(
        $logRecord['message'],
        $logRecord['context'],
        $logRecord['extra'] ?? [],
        Level::fromValue($logRecord['level']),
        $logRecord['context']['exception'] ?? null
    );

    $processedRecord = ($this->processor)($record);

    // Verify request data is in the context
    expect($processedRecord->context)->toHaveKey('request');
    expect($processedRecord->context['request']['method'])->toBe('POST');
    expect($processedRecord->context['request']['url'])->toContain('/api/posts');
});
