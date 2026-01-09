<?php

use Illuminate\Queue\Events\JobProcessing;
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

    $event = new JobProcessing('redis', $job);

    ($this->collector)($event);

    $jobContext = Context::get('job');

    expect($jobContext)->toHaveKeys(['name', 'class', 'queue', 'connection', 'attempts', 'payload']);
    expect($jobContext['name'])->toBe('App\Jobs\TestJob');
    expect($jobContext['queue'])->toBe('default');
    expect($jobContext['connection'])->toBe('redis');
    expect($jobContext['attempts'])->toBe(2);
});
