<?php

use Illuminate\Support\Facades\Http;
use Monolog\Level;
use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;
use Naoray\LaravelGithubMonolog\Issues\Formatter;
use Naoray\LaravelGithubMonolog\Issues\Handler;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->signatureGenerator = new DefaultSignatureGenerator();
    $this->formatter = new Formatter($this->signatureGenerator);
});

function fakeSuccessfulResponses(): void
{
    Http::fake([
        'api.github.com/search/issues*' => Http::response(['items' => []]),
        'api.github.com/repos/*/issues' => Http::response(['number' => 1]),
        'api.github.com/repos/*/issues/*/comments' => Http::response(),
    ]);
}

function createHandler(): Handler
{
    $handler = new Handler(
        repo: 'test/repo',
        token: 'test-token',
        labels: [],
        level: Level::Debug,
        bubble: true,
        signatureGenerator: test()->signatureGenerator
    );

    $handler->setFormatter(test()->formatter);
    return $handler;
}

test('it creates new github issue when no duplicate exists', function () {
    fakeSuccessfulResponses();
    $handler = createHandler();
    $record = createLogRecord('New issue');
    $handler->handle($record);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.github.com/repos/test/repo/issues' &&
            $request->hasHeader('Authorization', 'Bearer test-token') &&
            $request['title'] === '[ERROR] New issue';
    });
});

test('it comments on existing github issue', function () {
    Http::fake([
        'api.github.com/search/issues*' => Http::response([
            'items' => [
                ['number' => 123]
            ]
        ]),
        'api.github.com/repos/*/issues/*/comments' => Http::response(),
    ]);

    $handler = createHandler();
    $record = createLogRecord('Existing issue');
    $handler->handle($record);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.github.com/repos/test/repo/issues/123/comments' &&
            $request->hasHeader('Authorization', 'Bearer test-token') &&
            str_contains($request['body'], '# New Occurrence');
    });
});

test('it includes signature in issue search', function () {
    fakeSuccessfulResponses();
    $handler = createHandler();
    $record = createLogRecord('Test issue');
    $handler->handle($record);

    Http::assertSent(function ($request) use ($record) {
        return str_contains($request->url(), 'https://api.github.com/search/issues') &&
            str_contains($request['q'], "Signature: {$this->signatureGenerator->generate($record)}");
    });
});

test('it throws exception when issue creation fails', function () {
    Http::fake([
        'api.github.com/search/issues*' => Http::response(['items' => []]),
        'api.github.com/repos/*/issues' => Http::response([], 500),
    ]);

    $handler = createHandler();
    $record = createLogRecord('Failed issue');

    expect(fn() => $handler->handle($record))
        ->toThrow(RuntimeException::class, 'Failed to create GitHub issue');
});

test('it throws exception when issue search fails', function () {
    Http::fake([
        'api.github.com/search/issues*' => Http::response([], 500),
    ]);

    $handler = createHandler();
    $record = createLogRecord('Failed search');

    expect(fn() => $handler->handle($record))
        ->toThrow(RuntimeException::class, 'Failed to search GitHub issues');
});

test('it throws exception when comment creation fails', function () {
    Http::fake([
        'api.github.com/search/issues*' => Http::response([
            'items' => [
                ['number' => 123]
            ]
        ]),
        'api.github.com/repos/*/issues/*/comments' => Http::response([], 500),
    ]);

    $handler = createHandler();
    $record = createLogRecord('Failed comment');

    expect(fn() => $handler->handle($record))
        ->toThrow(RuntimeException::class, 'Failed to comment on GitHub issue');
});
