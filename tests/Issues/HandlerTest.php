<?php

namespace Tests\Issues;

use Illuminate\Support\Facades\Http;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Issues\Formatter;
use Naoray\LaravelGithubMonolog\Issues\Handler;

function createHandler(): Handler
{
    $handler = new Handler(
        repo: 'test/repo',
        token: 'test-token',
        labels: [],
        level: Level::Debug,
        bubble: true
    );

    $handler->setFormatter(new Formatter());
    return $handler;
}

function createRecord(): LogRecord
{
    return new LogRecord(
        datetime: new \DateTimeImmutable(),
        channel: 'test',
        level: Level::Error,
        message: 'Test message',
        context: [],
        extra: ['github_issue_signature' => 'test-signature']
    );
}

test('it creates new github issue when no duplicate exists', function () {
    $handler = createHandler();
    $record = createRecord();

    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['number' => 1]),
    ]);

    $handler->handle($record);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/repos/test/repo/issues')
            && $request->method() === 'POST';
    });
});

test('it comments on existing github issue', function () {
    $handler = createHandler();
    $record = createRecord();

    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => [['number' => 1]]]),
        'github.com/repos/test/repo/issues/1/comments' => Http::response(['id' => 1]),
    ]);

    $handler->handle($record);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/issues/1/comments')
            && $request->method() === 'POST';
    });
});

test('it includes signature in issue search', function () {
    $handler = createHandler();
    $record = createRecord();

    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['number' => 1]),
    ]);

    $handler->handle($record);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/search/issues')
            && str_contains($request['q'], 'test-signature');
    });
});

test('it throws exception when issue search fails', function () {
    $handler = createHandler();
    $record = createRecord();

    Http::fake([
        'github.com/search/issues*' => Http::response(['error' => 'Failed'], 500),
    ]);

    expect(fn() => $handler->handle($record))
        ->toThrow('Failed to search GitHub issues');
});

test('it throws exception when issue creation fails', function () {
    $handler = createHandler();
    $record = createRecord();

    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['error' => 'Failed'], 500),
    ]);

    expect(fn() => $handler->handle($record))
        ->toThrow('Failed to create GitHub issue');
});

test('it throws exception when comment creation fails', function () {
    $handler = createHandler();
    $record = createRecord();

    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => [['number' => 1]]]),
        'github.com/repos/test/repo/issues/1/comments' => Http::response(['error' => 'Failed'], 500),
    ]);

    expect(fn() => $handler->handle($record))
        ->toThrow('Failed to comment on GitHub issue');
});
