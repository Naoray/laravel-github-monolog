<?php

use Illuminate\Support\Facades\File;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\FileStore;

beforeEach(function () {
    $this->testPath = storage_path('logs/test-dedup.log');
    File::delete($this->testPath);
});

afterEach(function () {
    File::delete($this->testPath);
});

function createStore(string $prefix = 'test:', int $time = 60): FileStore
{
    return new FileStore(
        path: test()->testPath,
        prefix: $prefix,
        time: $time
    );
}

function createLogRecord(string $message = 'test', Level $level = Level::Error): LogRecord
{
    return new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'test',
        level: $level,
        message: $message,
        context: [],
        extra: [],
    );
}

// Base Store Tests
test('it can add and retrieve entries', function () {
    $store = createStore();
    $record = createLogRecord();

    $store->add($record, 'test-signature');
    $entries = $store->get();

    expect($entries)->toHaveCount(1)
        ->and($entries[0])->toEndWith('test-signature');
});

test('it removes expired entries', function () {
    $store = createStore(time: 1);
    $record = createLogRecord();

    $store->add($record, 'test-signature');
    sleep(2);

    $store->cleanup();
    expect($store->get())->toBeEmpty();
});

test('it keeps valid entries', function () {
    $store = createStore(time: 5);
    $record = createLogRecord();

    $store->add($record, 'test-signature');
    $entries = $store->get();

    expect($entries)->toHaveCount(1);
});

test('it handles multiple entries', function () {
    $store = createStore();
    $record1 = createLogRecord('test1');
    $record2 = createLogRecord('test2');

    $store->add($record1, 'signature1');
    $store->add($record2, 'signature2');

    expect($store->get())->toHaveCount(2);
});

// FileStore Specific Tests
test('it creates directory if not exists', function () {
    $path = storage_path('logs/nested/test-dedup.log');
    File::deleteDirectory(dirname($path));

    new FileStore($path);

    expect(File::exists(dirname($path)))->toBeTrue();
    File::deleteDirectory(dirname($path));
});

test('it handles concurrent access', function () {
    $store1 = createStore();
    $store2 = createStore();
    $record = createLogRecord();

    $store1->add($record, 'signature1');
    $store2->add($record, 'signature2');

    expect($store1->get())->toHaveCount(2);
});

test('it handles corrupted file', function () {
    File::put(test()->testPath, 'invalid content');

    $store = createStore();
    $record = createLogRecord();

    $store->add($record, 'test-signature');
    $entries = $store->get();

    expect($entries)->not->toBeEmpty()
        ->and($entries[array_key_first($entries)])->toEndWith('test-signature');
});

test('it handles file permissions', function () {
    $store = createStore();
    $record = createLogRecord();

    $store->add($record, 'test-signature');

    expect(substr(sprintf('%o', fileperms(test()->testPath)), -4))->toBe('0644');
});

test('it maintains file integrity after cleanup', function () {
    $store = createStore(time: 1);
    $record = createLogRecord();

    $store->add($record, 'signature1');
    sleep(2);
    $store->add($record, 'signature2');

    $store->cleanup();

    $content = File::get(test()->testPath);

    expect(explode(PHP_EOL, trim($content)))->toHaveCount(1)
        ->and($content)->toEndWith('signature2');
});
