<?php

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\JobContextCollector;

beforeEach(function () {
    $this->collector = new JobContextCollector;
});

afterEach(function () {
    Context::flush();
});

it('collects job context', function () {
    $job = Mockery::mock('Illuminate\Contracts\Queue\Job');
    $job->shouldReceive('getName')->andReturn('App\Jobs\TestJob');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('getConnectionName')->andReturn('redis');
    $job->shouldReceive('attempts')->andReturn(2);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Jobs\TestJob',
        'data' => ['key' => 'value'],
    ]);

    $event = new JobExceptionOccurred('redis', $job, new \RuntimeException('Test exception'));

    ($this->collector)($event);

    $jobContext = Context::get('job');

    expect($jobContext)->toHaveKeys(['name', 'class', 'queue', 'connection', 'attempts', 'payload']);
    expect($jobContext['name'])->toBe('App\Jobs\TestJob');
    expect($jobContext['queue'])->toBe('default');
    expect($jobContext['connection'])->toBe('redis');
    expect($jobContext['attempts'])->toBe(2);
});

it('truncates long serialized command strings in payload', function () {
    $longSerializedCommand = str_repeat('O:32:"App\\Jobs\\TestJob":3:{s:6:"tries";', 50);

    $job = Mockery::mock('Illuminate\Contracts\Queue\Job');
    $job->shouldReceive('getName')->andReturn('App\Jobs\TestJob');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('getConnectionName')->andReturn('redis');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Jobs\TestJob',
        'uuid' => 'test-uuid-1234',
        'job' => 'Illuminate\Queue\CallQueuedHandler@call',
        'maxTries' => 3,
        'timeout' => 120,
        'data' => [
            'commandName' => 'App\Jobs\TestJob',
            'command' => $longSerializedCommand,
        ],
    ]);

    $event = new JobExceptionOccurred('redis', $job, new \RuntimeException('Test exception'));

    ($this->collector)($event);

    $jobContext = Context::get('job');
    $payload = $jobContext['payload'];

    // The command field should be truncated
    expect(strlen($payload['data']['command']))->toBeLessThan(strlen($longSerializedCommand));
    expect($payload['data']['command'])->toEndWith('... [truncated]');
    // Truncated to 500 chars + the "... [truncated]" marker
    expect(strlen($payload['data']['command']))->toBe(500 + strlen('... [truncated]'));

    // Other payload fields should be preserved as-is
    expect($payload['displayName'])->toBe('App\Jobs\TestJob');
    expect($payload['uuid'])->toBe('test-uuid-1234');
    expect($payload['job'])->toBe('Illuminate\Queue\CallQueuedHandler@call');
    expect($payload['maxTries'])->toBe(3);
    expect($payload['timeout'])->toBe(120);
    expect($payload['data']['commandName'])->toBe('App\Jobs\TestJob');
});

it('does not truncate short string values in payload', function () {
    $job = Mockery::mock('Illuminate\Contracts\Queue\Job');
    $job->shouldReceive('getName')->andReturn('App\Jobs\TestJob');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('getConnectionName')->andReturn('redis');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Jobs\TestJob',
        'uuid' => 'test-uuid-1234',
        'data' => [
            'commandName' => 'App\Jobs\TestJob',
            'command' => 'short-command-value',
        ],
    ]);

    $event = new JobExceptionOccurred('redis', $job, new \RuntimeException('Test exception'));

    ($this->collector)($event);

    $jobContext = Context::get('job');
    $payload = $jobContext['payload'];

    // Short values should not be truncated
    expect($payload['data']['command'])->toBe('short-command-value');
    expect($payload['displayName'])->toBe('App\Jobs\TestJob');
});

it('preserves non-string values in payload during truncation', function () {
    $job = Mockery::mock('Illuminate\Contracts\Queue\Job');
    $job->shouldReceive('getName')->andReturn('App\Jobs\TestJob');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('getConnectionName')->andReturn('redis');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Jobs\TestJob',
        'maxTries' => 3,
        'timeout' => null,
        'attempts' => 1,
        'tags' => ['tag1', 'tag2'],
        'data' => [
            'commandName' => 'App\Jobs\TestJob',
        ],
    ]);

    $event = new JobExceptionOccurred('redis', $job, new \RuntimeException('Test exception'));

    ($this->collector)($event);

    $jobContext = Context::get('job');
    $payload = $jobContext['payload'];

    // Integer, null, and array values should be preserved
    expect($payload['maxTries'])->toBe(3);
    expect($payload['timeout'])->toBeNull();
    expect($payload['attempts'])->toBe(1);
    expect($payload['tags'])->toBe(['tag1', 'tag2']);
});
