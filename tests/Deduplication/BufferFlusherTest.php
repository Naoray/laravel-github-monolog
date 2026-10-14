<?php

use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Deduplication\BufferFlusher;
use Naoray\LaravelGithubMonolog\Deduplication\DeduplicationHandler;
use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;

class BufferedGithubTestChannel
{
    public static TestHandler $inner;

    public static ?DeduplicationHandler $handler = null;

    public function __invoke(array $config): Logger
    {
        static::$handler = new DeduplicationHandler(
            handler: static::$inner,
            signatureGenerator: new DefaultSignatureGenerator,
            store: 'array',
            bufferLimit: $config['buffer_limit'] ?? 0,
        );

        return new Logger('github', [static::$handler]);
    }
}

class ThrowingTestHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        throw new RuntimeException('GitHub is down');
    }
}

beforeEach(function () {
    Cache::store('array')->clear();

    BufferedGithubTestChannel::$inner = new TestHandler;
    BufferedGithubTestChannel::$handler = null;

    config()->set('logging.channels.github-buffered', [
        'driver' => 'custom',
        'via' => BufferedGithubTestChannel::class,
        'buffer_limit' => 2,
    ]);
    config()->set('logging.channels.github-stack', [
        'driver' => 'stack',
        'channels' => ['github-buffered'],
    ]);
});

test('a buffered record is not sent until something flushes it', function () {
    Log::channel('github-buffered')->error('Job failed');

    expect(BufferedGithubTestChannel::$inner->getRecords())->toBeEmpty();
});

test('the flusher sends records buffered on resolved channels', function () {
    Log::channel('github-buffered')->error('Job failed');

    app(BufferFlusher::class)->flush();

    expect(BufferedGithubTestChannel::$inner->getRecords())->toHaveCount(1)
        ->and(BufferedGithubTestChannel::$inner->getRecords()[0]->message)->toBe('Job failed');
});

test('the flusher reaches handlers through stack channels', function () {
    Log::channel('github-stack')->error('Job failed via stack');

    app(BufferFlusher::class)->flush();

    expect(BufferedGithubTestChannel::$inner->getRecords())->toHaveCount(1);
});

test('the flusher does not resolve channels that were never used', function () {
    app(BufferFlusher::class)->flush();

    expect(BufferedGithubTestChannel::$handler)->toBeNull();
});

test('the worker loop flushes buffered records', function () {
    Log::channel('github-buffered')->error('Job failed');

    $result = Event::until(new Looping('redis', 'default'));

    expect(BufferedGithubTestChannel::$inner->getRecords())->toHaveCount(1)
        // until() pauses the worker when a listener returns false.
        ->and($result)->not->toBeFalse();
});

test('a stopping worker flushes buffered records before it is killed', function () {
    Log::channel('github-buffered')->error('Job timed out');

    Event::dispatch(new WorkerStopping(1));

    expect(BufferedGithubTestChannel::$inner->getRecords())->toHaveCount(1);
});

test('an octane request termination flushes buffered records', function () {
    Log::channel('github-buffered')->error('Request failed');

    Event::dispatch('Laravel\Octane\Events\RequestTerminated');

    expect(BufferedGithubTestChannel::$inner->getRecords())->toHaveCount(1);
});

test('a failing flush never throws and clears the buffer', function () {
    BufferedGithubTestChannel::$inner = new TestHandler;
    $handler = new DeduplicationHandler(
        handler: new ThrowingTestHandler,
        signatureGenerator: new DefaultSignatureGenerator,
        store: 'array',
    );
    Log::extend('throwing', fn () => new Logger('github', [$handler]));
    config()->set('logging.channels.github-throwing', ['driver' => 'throwing']);

    Log::channel('github-throwing')->error('Job failed');

    Event::until(new Looping('redis', 'default'));

    // A second flush would throw again if the record were still buffered.
    expect(fn () => $handler->flush())->not->toThrow(RuntimeException::class);
});
