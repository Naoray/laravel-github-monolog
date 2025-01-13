<?php

use Monolog\Handler\TestHandler;
use Naoray\LaravelGithubMonolog\Deduplication\DeduplicationHandler;
use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\FileStore;

use function Pest\Laravel\travel;

beforeEach(function () {
    $this->testHandler = new TestHandler;
    $this->tempFile = sys_get_temp_dir().'/dedup-test-'.uniqid().'.log';
});

afterEach(function () {
    @unlink($this->tempFile);
});

test('deduplication respects time window', function () {
    $store = new FileStore($this->tempFile, time: 1);
    $handler = new DeduplicationHandler(
        handler: $this->testHandler,
        store: $store,
        signatureGenerator: new DefaultSignatureGenerator,
    );

    $record = createLogRecord();

    $handler->handle($record);
    $handler->flush();
    expect($this->testHandler->getRecords())->toHaveCount(1);

    travel(2)->seconds();

    $handler->handle($record);
    $handler->flush();
    expect($this->testHandler->getRecords())->toHaveCount(2);
});

test('deduplicates records with same signature', function () {
    $store = new FileStore($this->tempFile);
    $handler = new DeduplicationHandler(
        handler: $this->testHandler,
        store: $store,
        signatureGenerator: new DefaultSignatureGenerator
    );

    $record = createLogRecord();

    $handler->handle($record);
    $handler->handle($record);
    $handler->flush();
    expect($this->testHandler->getRecords())->toHaveCount(1);
});

test('different messages create different signatures', function () {
    $store = new FileStore($this->tempFile);
    $handler = new DeduplicationHandler(
        handler: $this->testHandler,
        store: $store,
        signatureGenerator: new DefaultSignatureGenerator
    );

    $record1 = createLogRecord('First message');
    $record2 = createLogRecord('Second message');

    $handler->handle($record1);
    $handler->handle($record2);
    $handler->flush();
    expect($this->testHandler->getRecords())->toHaveCount(2);
});
