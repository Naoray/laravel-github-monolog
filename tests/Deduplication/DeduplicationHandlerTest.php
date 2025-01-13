<?php

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Deduplication\DeduplicationHandler;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\FileStore;
use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;

beforeEach(function () {
    $this->testHandler = new TestHandler;
    $this->tempFile = sys_get_temp_dir() . '/dedup-test-' . uniqid() . '.log';
});

afterEach(function () {
    @unlink($this->tempFile);
});

function createLogRecord(string $message = 'Test'): LogRecord
{
    return new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: $message,
        context: [],
        extra: [],
    );
}

test('deduplication respects time window', function () {
    $store = new FileStore($this->tempFile);
    $handler = new DeduplicationHandler(
        handler: $this->testHandler,
        store: $store,
        signatureGenerator: new DefaultSignatureGenerator(),
        time: 1
    );

    $record = createLogRecord();

    $handler->handle($record);
    expect($this->testHandler->getRecords())->toHaveCount(1);

    sleep(2);
    $handler->handle($record);
    expect($this->testHandler->getRecords())->toHaveCount(2);
});

test('deduplicates records with same signature', function () {
    $store = new FileStore($this->tempFile);
    $handler = new DeduplicationHandler(
        handler: $this->testHandler,
        store: $store,
        signatureGenerator: new DefaultSignatureGenerator()
    );

    $record = createLogRecord();

    $handler->handle($record);
    $handler->handle($record);

    expect($this->testHandler->getRecords())->toHaveCount(1);
});

test('different messages create different signatures', function () {
    $store = new FileStore($this->tempFile);
    $handler = new DeduplicationHandler(
        handler: $this->testHandler,
        store: $store,
        signatureGenerator: new DefaultSignatureGenerator()
    );

    $record1 = createLogRecord('First message');
    $record2 = createLogRecord('Second message');

    $handler->handle($record1);
    $handler->handle($record2);

    expect($this->testHandler->getRecords())->toHaveCount(2);
});
