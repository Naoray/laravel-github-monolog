<?php

namespace Naoray\GithubMonolog\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\GithubIssueFormatter;
use Naoray\LaravelGithubMonolog\GithubIssueLoggerHandler;
use RuntimeException;

beforeEach(function () {
    $this->handler = (new GithubIssueLoggerHandler(
        repo: 'test/repo',
        token: 'fake-token',
        labels: ['bug'],
        level: Level::Error
    ))->setFormatter(new GithubIssueFormatter);
});

function createLogRecord(string $message = 'Test error', array $context = [], Level $level = Level::Error): LogRecord
{
    return new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'test',
        level: $level,
        message: $message,
        context: $context,
        extra: []
    );
}

function createFormattedRecord(GithubIssueLoggerHandler $handler, string $message = 'Test error', array $context = [], Level $level = Level::Error): LogRecord
{
    $baseRecord = createLogRecord($message, $context, $level);

    return new LogRecord(
        datetime: $baseRecord->datetime,
        channel: $baseRecord->channel,
        level: $baseRecord->level,
        message: $baseRecord->message,
        context: $baseRecord->context,
        extra: $baseRecord->extra,
        formatted: $handler->getFormatter()->format($baseRecord)
    );
}

test('it creates a new issue when no matching issue exists', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['number' => 1], 201),
    ]);

    /** @phpstan-ignore-next-line */
    $this->handler->handle(createFormattedRecord($this->handler));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.github.com/repos/test/repo/issues' &&
            $request->method() === 'POST' &&
            $request['labels'] === ['github-issue-logger', 'bug'];
    });
});

test('it adds comment to existing issue when signature matches', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response([
            'items' => [
                ['number' => 123],
            ],
        ]),
        'github.com/repos/test/repo/issues/123/comments' => Http::response([], 201),
    ]);

    $this->handler->handle(createFormattedRecord($this->handler));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.github.com/repos/test/repo/issues/123/comments' &&
            $request->method() === 'POST';
    });
});

test('it throws exception when github api fails', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response([], 500),
    ]);

    expect(fn () => $this->handler->handle(createFormattedRecord($this->handler)))
        ->toThrow(\RuntimeException::class, 'Failed to search GitHub issues');
});

test('it merges default label with custom labels', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['number' => 1], 201),
    ]);

    $handler = (new GithubIssueLoggerHandler(
        repo: 'test/repo',
        token: 'fake-token',
        labels: ['custom-label', 'another-label'],
        level: Level::Error
    ))->setFormatter(new GithubIssueFormatter);

    /** @phpstan-ignore-next-line */
    $handler->handle(createFormattedRecord($handler));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.github.com/repos/test/repo/issues' &&
            $request->method() === 'POST' &&
            $request['labels'] === ['github-issue-logger', 'custom-label', 'another-label'];
    });
});

test('it handles exceptions in log records', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['number' => 1], 201),
    ]);

    $exception = new RuntimeException('Test exception');
    $record = createFormattedRecord(
        $this->handler,
        'Error occurred',
        ['exception' => $exception]
    );

    $this->handler->handle($record);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.github.com/repos/test/repo/issues' &&
            str_contains($request['body'], 'Test exception') &&
            str_contains($request['body'], 'Stack Trace:');
    });
});

test('it handles nested exceptions', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['number' => 1], 201),
    ]);

    $previousException = new RuntimeException('Previous exception');
    $exception = new RuntimeException('Main exception', 0, $previousException);
    $record = createFormattedRecord(
        $this->handler,
        'Error occurred',
        ['exception' => $exception]
    );

    $this->handler->handle($record);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.github.com/repos/test/repo/issues' &&
            str_contains($request['body'], 'Main exception') &&
            str_contains($request['body'], 'Previous Exceptions') &&
            str_contains($request['body'], 'Previous exception');
    });
});

test('it ignores records below minimum level', function () {
    $record = createFormattedRecord(
        $this->handler,
        'Debug message',
        level: Level::Debug
    );

    $result = $this->handler->handle($record);

    expect($result)->toBeFalse();
    Http::assertNothingSent();
});

test('it includes extra data in issue body', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['number' => 1], 201),
    ]);

    $record = new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: 'Test error',
        context: [],
        extra: ['server' => 'production', 'user_id' => 123],
        formatted: $this->handler->getFormatter()->format(createLogRecord())
    );

    $this->handler->handle($record);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.github.com/repos/test/repo/issues' &&
            str_contains($request['body'], '"server": "production"') &&
            str_contains($request['body'], '"user_id": 123');
    });
});

test('it creates unique signatures for different errors', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['number' => 1], 201),
    ]);

    $record1 = createFormattedRecord($this->handler, 'Error 1');
    $record2 = createFormattedRecord($this->handler, 'Error 2');

    $this->handler->handle($record1);
    $this->handler->handle($record2);

    Http::assertSentCount(4); // 2 searches + 2 issue creations
});

test('it fails gracefully when creating issue fails', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response([], 500),
    ]);

    $record = createFormattedRecord($this->handler);

    expect(fn () => $this->handler->handle($record))
        ->toThrow(\RuntimeException::class, 'Failed to create GitHub issue');
});

test('it limits the number of previous exceptions', function () {
    Http::fake([
        'github.com/search/issues*' => Http::response(['items' => []]),
        'github.com/repos/test/repo/issues' => Http::response(['number' => 1], 201),
    ]);

    // Create a chain of 10 nested exceptions
    // Start with the innermost exception
    $exception = new RuntimeException('Exception 1');
    for ($i = 2; $i <= 10; $i++) {
        $exception = new RuntimeException("Exception {$i}", 0, $exception);
    }

    $record = createFormattedRecord(
        $this->handler,
        'Error occurred',
        ['exception' => $exception]
    );

    $this->handler->handle($record);

    Http::assertSent(function (Request $request) {
        if (! str_contains($request->url(), '/repos/test/repo/issues')) {
            return false;
        }

        $data = $request->data();

        // Should contain the main (last) exception
        expect($data['body'])->toContain('Exception 10')
            // Should contain only the first 3 previous exceptions (7, 8, 9)
            ->and($data['body'])->toContain('Exception 9')
            ->and($data['body'])->toContain('Exception 8')
            ->and($data['body'])->toContain('Exception 7')
            // Should NOT contain earlier exceptions due to limit
            ->and($data['body'])->not->toContain('Exception 6')
            ->and($data['body'])->not->toContain('Exception 5')
            // Should indicate truncation
            ->and($data['body'])->toContain('Additional previous exceptions were truncated');

        return true;
    });
});
