<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\DatabaseStore;

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

    $store->add($record, 'test-signature');
    $entries = $store->get();

    expect($entries)->toHaveCount(1)
        ->and($entries[0])->toEndWith('test-signature');
});

test('it removes expired entries', function () {
    $store = createDatabaseStore(time: 1);
    $record = createLogRecord();

    $store->add($record, 'test-signature');
    sleep(2);

    expect($store->get())->toBeEmpty();
});

test('it keeps valid entries', function () {
    $store = createDatabaseStore(time: 5);
    $record = createLogRecord();

    $store->add($record, 'test-signature');
    $entries = $store->get();

    expect($entries)->toHaveCount(1);
});

test('it handles multiple entries', function () {
    $store = createDatabaseStore();
    $record1 = createLogRecord('test1');
    $record2 = createLogRecord('test2');

    $store->add($record1, 'signature1');
    $store->add($record2, 'signature2');

    expect($store->get())->toHaveCount(2);
});

// DatabaseStore Specific Tests
test('it creates table if not exists', function () {
    createDatabaseStore();

    expect(Schema::hasTable('github_monolog_deduplication'))->toBeTrue();
});

test('it can use custom table', function () {
    $store = new DatabaseStore(
        connection: 'sqlite',
        table: 'custom_dedup',
        prefix: 'test:',
        time: 60
    );

    $record = createLogRecord();
    $store->add($record, 'test-signature');

    expect(Schema::hasTable('custom_dedup'))->toBeTrue()
        ->and(DB::table('custom_dedup')->get())->toHaveCount(1);
});

test('it cleans up expired entries from database', function () {
    $store = createDatabaseStore(time: 1);
    $record = createLogRecord();

    $store->add($record, 'test-signature');
    expect(DB::table('github_monolog_deduplication')->get())->toHaveCount(1);

    sleep(2);
    $store->cleanup();

    expect(DB::table('github_monolog_deduplication')->get())->toBeEmpty();
});

test('it only returns entries for specific prefix', function () {
    $store1 = createDatabaseStore('prefix1:');
    $store2 = createDatabaseStore('prefix2:');
    $record = createLogRecord();

    $store1->add($record, 'signature1');
    $store2->add($record, 'signature2');

    expect($store1->get())->toHaveCount(1)
        ->and($store2->get())->toHaveCount(1);
});
