<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\DatabaseStore;
use function Pest\Laravel\travel;

beforeEach(function () {
    // Ensure we're using SQLite for testing
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
});

afterEach(function () {
    Schema::dropIfExists('github_monolog_deduplication');
    Schema::dropIfExists('custom_dedup');
});

// Base Store Tests
test('it can add and retrieve entries', function () {
    $store = createDatabaseStore();
    $record = createLogRecord();
    $signature = 'test-signature';

    $store->add($record, $signature);

    expect($store->get())->toHaveCount(1);
});

test('it removes expired entries', function () {
    $store = createDatabaseStore(time: 1);
    $record = createLogRecord();
    $signature = 'test-signature';

    $store->add($record, $signature);
    travel(2)->seconds();

    expect($store->get())->toBeEmpty();
});

test('it keeps valid entries', function () {
    $store = createDatabaseStore();
    $record = createLogRecord();
    $signature = 'test-signature';

    $store->add($record, $signature);

    expect($store->get())->toHaveCount(1);
});

test('it handles multiple entries', function () {
    $store = createDatabaseStore();
    $record = createLogRecord();

    $store->add($record, 'signature-1');
    $store->add($record, 'signature-2');

    expect($store->get())->toHaveCount(2);
});

// DatabaseStore Specific Tests
test('it creates table if not exists', function () {
    $store = createDatabaseStore();
    expect(Schema::connection('sqlite')->hasTable('github_monolog_deduplication'))->toBeTrue();
});

test('it can use custom table', function () {
    $store = new DatabaseStore(
        connection: 'sqlite',
        table: 'custom_dedup',
        time: 60
    );

    $record = createLogRecord();
    $signature = 'test-signature';

    $store->add($record, $signature);

    expect($store->get())->toHaveCount(1);
    expect(Schema::connection('sqlite')->hasTable('custom_dedup'))->toBeTrue();
});

test('it cleans up expired entries from database', function () {
    $store = createDatabaseStore(time: 1);
    $record = createLogRecord();

    $store->add($record, 'signature-1');
    $store->add($record, 'signature-2');
    travel(2)->seconds();

    $store->cleanup();

    expect($store->get())->toBeEmpty();
});
