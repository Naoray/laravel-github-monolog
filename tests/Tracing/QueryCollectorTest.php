<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\QueryCollector;

beforeEach(function () {
    $this->collector = new QueryCollector;
    Config::set('logging.channels.github.tracing.queries', ['enabled' => true, 'limit' => 10]);
});

afterEach(function () {
    Context::flush();
});

it('collects query data', function () {
    $connection = Mockery::mock('Illuminate\Database\Connection');
    $connection->shouldReceive('getName')->andReturn('mysql');

    $event = new QueryExecuted(
        sql: 'SELECT * FROM users WHERE id = ?',
        bindings: [1],
        time: 10.5,
        connection: $connection
    );

    ($this->collector)($event);

    $queries = Context::get('queries');

    expect($queries)->toHaveCount(1);
    expect($queries[0])->toHaveKeys(['sql', 'bindings', 'time', 'connection']);
    expect($queries[0]['sql'])->toBe('SELECT * FROM users WHERE id = ?');
    expect($queries[0]['bindings'])->toBe([1]);
    expect($queries[0]['time'])->toBe(10.5);
    expect($queries[0]['connection'])->toBe('mysql');
});

it('respects query limit', function () {
    Config::set('logging.channels.github.tracing.queries', ['enabled' => true, 'limit' => 2]);

    $connection = Mockery::mock('Illuminate\Database\Connection');
    $connection->shouldReceive('getName')->andReturn('mysql');

    for ($i = 0; $i < 5; $i++) {
        $event = new QueryExecuted(
            sql: "SELECT * FROM users WHERE id = {$i}",
            bindings: [],
            time: 1.0,
            connection: $connection
        );
        ($this->collector)($event);
    }

    $queries = Context::get('queries');

    expect($queries)->toHaveCount(2);
    expect($queries[0]['sql'])->toContain('id = 3');
    expect($queries[1]['sql'])->toContain('id = 4');
});

it('does not collect when disabled', function () {
    Config::set('logging.channels.github.tracing.queries', ['enabled' => false]);

    $connection = Mockery::mock('Illuminate\Database\Connection');
    $connection->shouldReceive('getName')->andReturn('mysql');

    $event = new QueryExecuted(
        sql: 'SELECT * FROM users',
        bindings: [],
        time: 1.0,
        connection: $connection
    );

    ($this->collector)($event);

    expect(Context::has('queries'))->toBeFalse();
});
