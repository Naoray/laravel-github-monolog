<?php

use Illuminate\Support\Facades\Redis;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\RedisStore;

use function Pest\Laravel\travel;

beforeEach(function () {
    // Configure Redis for testing
    config()->set('database.redis.default', [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => 0,
    ]);

    // Configure second Redis connection for testing
    config()->set('database.redis.other', [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => 1,
    ]);

    // Clear test keys
    Redis::del('test:dedup');
    Redis::del('custom:dedup');
    Redis::connection('other')->del('test:dedup');
});

afterEach(function () {
    Redis::del('test:dedup');
    Redis::del('custom:dedup');
    Redis::connection('other')->del('test:dedup');
});

// Base Store Tests
test('it can add and retrieve entries', function () {
    $store = createRedisStore();
    $record = createLogRecord();

    $store->add($record, 'test-signature');
    $entries = $store->get();

    expect($entries)->toHaveCount(1)
        ->and($entries[0])->toEndWith('test-signature');
});

test('it removes expired entries', function () {
    $store = createRedisStore(time: 1);
    $record = createLogRecord();

    $store->add($record, 'test-signature');

    travel(2)->seconds();

    $store->cleanup();

    expect($store->get())->toBeEmpty();
});

test('it keeps valid entries', function () {
    $store = createRedisStore(time: 5);
    $record = createLogRecord();

    $store->add($record, 'test-signature');
    $entries = $store->get();

    expect($entries)->toHaveCount(1);
});

test('it handles multiple entries', function () {
    $store = createRedisStore();
    $record1 = createLogRecord('test1');
    $record2 = createLogRecord('test2');

    $store->add($record1, 'signature1');
    $store->add($record2, 'signature2');

    expect($store->get())->toHaveCount(2);
});

// Redis Store Specific Tests
test('it uses correct redis key', function () {
    $store = createRedisStore('custom:');
    $record = createLogRecord();

    $store->add($record, 'test-signature');

    expect(Redis::exists('custom:dedup'))->toBeGreaterThan(0);
});

test('it can use different redis connection', function () {
    $store = new RedisStore(
        connection: 'other',
        prefix: 'test:',
        time: 60
    );

    $record = createLogRecord();
    $store->add($record, 'test-signature');

    expect(Redis::connection('other')->exists('test:dedup'))->toBeGreaterThan(0);
});

test('it properly cleans up expired entries', function () {
    $store = createRedisStore(time: 1);
    $record = createLogRecord();

    // Add an entry that will expire
    $store->add($record, 'test-signature');

    // Verify it exists
    expect($store->get())->toHaveCount(1);

    travel(2)->seconds();

    $store->cleanup();

    // Verify Redis directly
    expect(Redis::zcount($store->getKey(), '-inf', '+inf'))
        ->toBe(0)
        ->and($store->get())->toBeEmpty();
});
