<?php

namespace Tests\Issues;

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Auth\GithubAppTokenProvider;
use Naoray\LaravelGithubMonolog\Issues\Formatters\IssueFormatter;
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

    $handler->setFormatter(app()->make(IssueFormatter::class));

    return $handler;
}

function createRecord(): LogRecord
{
    return new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: 'Test message',
        context: [],
        extra: ['github_issue_signature' => 'test-signature']
    );
}

beforeEach(function () {
    Http::preventStrayRequests();
});

test('it creates new github issue when no duplicate exists', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['number' => 1]),
    ]);

    $handler = createHandler();
    $record = createRecord();

    $handler->handle($record);

    Http::assertSent(function (Request $request) {
        return str($request->url())->endsWith('/repos/test/repo/issues');
    });
});

test('it comments on existing github issue', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => [['number' => 1]]]),
        'github.com/repos/test/repo/issues/1/comments' => Http::response(['id' => 1]),
    ]);

    $handler = createHandler();
    $record = createRecord();

    $handler->handle($record);

    Http::assertSent(function ($request) {
        return str($request->url())->endsWith('/issues/1/comments');
    });
});

test('it includes signature in issue search', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['number' => 1]),
    ]);

    $handler = createHandler();
    $record = createRecord();

    $handler->handle($record);

    Http::assertSent(function ($request) {
        return str($request->url())->contains('/search/issues')
            && str_contains($request->data()['q'], 'test-signature');
    });
});

test('it throws exception when issue search fails', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['error' => 'Failed'], 500),
    ]);

    $handler = createHandler();
    $record = createRecord();

    $handler->handle($record);
})->throws(RequestException::class, exceptionCode: 500);

test('it throws exception when issue creation fails', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['error' => 'Failed'], 500),
    ]);

    $handler = createHandler();
    $record = createRecord();

    $handler->handle($record);
})->throws(RequestException::class, exceptionCode: 500);

test('it throws exception when comment creation fails', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => [['number' => 1]]]),
        'github.com/repos/test/repo/issues/1/comments' => Http::response(['error' => 'Failed'], 500),
    ]);

    $handler = createHandler();
    $record = createRecord();

    $handler->handle($record);
})->throws(RequestException::class, exceptionCode: 500);

test('it creates fallback issue when 4xx error occurs', function () {
    $errorMessage = 'Validation failed for the issue';

    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::sequence()
            ->push(['error' => $errorMessage], 422)
            ->push(['number' => 1]),
    ]);

    $handler = createHandler();
    $record = createRecord();

    $handler->handle($record);

    Http::assertSent(function ($request) {
        return str($request->url())->endsWith('/repos/test/repo/issues')
            && ! str_contains($request->data()['title'], '[GitHub Monolog Error]');
    });

    Http::assertSent(function ($request) use ($errorMessage) {
        return str($request->url())->endsWith('/repos/test/repo/issues')
            && str_contains($request->data()['title'], '[GitHub Monolog Error]')
            && str_contains($request->data()['body'], $errorMessage)
            && in_array('monolog-integration-error', $request->data()['labels']);
    });
});

test('it refreshes github app authentication between writes after the cached token expires', function () {
    $authenticatedRequests = [];
    $installationTokenRequests = 0;

    Http::fake(function (Request $request) use (&$authenticatedRequests, &$installationTokenRequests) {
        if (str($request->url())->endsWith('/app/installations/67890/access_tokens')) {
            $installationTokenRequests++;

            return Http::response(['token' => "installation-token-{$installationTokenRequests}"]);
        }

        $authenticatedRequests[] = $request->header('Authorization')[0];

        if (str($request->url())->contains('/search/issues')) {
            return Http::response(['items' => []]);
        }

        return Http::response(['number' => 1]);
    });

    $handler = new Handler(
        repo: 'test/repo',
        token: new GithubAppTokenProvider(
            clientId: '12345',
            installationId: '67890',
            privateKey: generateTestPrivateKey(),
            cacheStore: 'array',
        ),
        level: Level::Debug,
    );
    $handler->setFormatter(app()->make(IssueFormatter::class));
    $record = createRecord();

    Carbon::setTestNow(Carbon::now());

    try {
        $handler->handle($record);
        Carbon::setTestNow(Carbon::now()->addMinutes(51));
        $handler->handle($record);
    } finally {
        Carbon::setTestNow();
    }

    expect($installationTokenRequests)
        ->toBe(2)
        ->and($authenticatedRequests)
        ->toBe([
            'Bearer installation-token-1',
            'Bearer installation-token-1',
            'Bearer installation-token-2',
            'Bearer installation-token-2',
        ]);
});

function createLegacyRecord(): LogRecord
{
    return new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: 'Test message',
        context: [],
        extra: ['github_issue_signature' => 'new-signature', 'github_issue_legacy_signature' => 'legacy-signature'],
    );
}

function searchesFor(string $signature): \Closure
{
    return fn (Request $request) => str($request->url())->contains('/search/issues')
        && str_contains($request->data()['q'] ?? '', "\"Signature: {$signature}\"");
}

test('it comments on the most recently updated pre-3.11 issue with the new signature', function () {
    Http::fake(function (Request $request) {
        return match (true) {
            searchesFor('new-signature')($request) => Http::response(['items' => []]),
            searchesFor('legacy-signature')($request) => Http::response(['items' => [['number' => 7], ['number' => 3]]]),
            default => Http::response(['id' => 1]),
        };
    });

    createHandler()->handle(createLegacyRecord());

    Http::assertSent(fn (Request $request) => searchesFor('legacy-signature')($request)
        && $request->data()['sort'] === 'updated'
        && $request->data()['order'] === 'desc');
    Http::assertSent(fn (Request $request) => str($request->url())->endsWith('/issues/7/comments')
        && str_contains($request->data()['body'], '<!-- Signature: new-signature -->'));
    Http::assertNotSent(fn (Request $request) => str($request->url())->endsWith('/repos/test/repo/issues'));
});

test('it does not search for the legacy signature when the new one matches', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => [['number' => 1]]]),
        'github.com/repos/test/repo/issues/1/comments' => Http::response(['id' => 1]),
    ]);

    createHandler()->handle(createLegacyRecord());

    Http::assertNotSent(searchesFor('legacy-signature'));
});

test('it opens a new issue when no pre-3.11 issue matches', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['number' => 1]),
    ]);

    createHandler()->handle(createLegacyRecord());

    Http::assertSent(searchesFor('legacy-signature'));
    Http::assertSent(fn (Request $request) => str($request->url())->endsWith('/repos/test/repo/issues')
        && str_contains($request->data()['body'], '<!-- Signature: new-signature -->'));
});

test('it opens a new issue when the legacy search fails', function (int $status) {
    Http::fake(function (Request $request) use ($status) {
        return match (true) {
            searchesFor('new-signature')($request) => Http::response(['items' => []]),
            searchesFor('legacy-signature')($request) => Http::response(['message' => 'Failed'], $status),
            default => Http::response(['number' => 1]),
        };
    });

    createHandler()->handle(createLegacyRecord());

    Http::assertSent(fn (Request $request) => str($request->url())->endsWith('/repos/test/repo/issues')
        && ! str_contains($request->data()['title'], '[GitHub Monolog Error]'));
})->with([403, 500]);

test('records without a legacy signature search only once', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['number' => 1]),
    ]);

    createHandler()->handle(createRecord());

    Http::assertSentCount(2);
});
