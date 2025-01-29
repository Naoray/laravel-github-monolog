<?php

use Illuminate\Support\Facades\Cache;
use Monolog\Handler\TestHandler;
use Naoray\LaravelGithubMonolog\Deduplication\DeduplicationHandler;
use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;

use function Pest\Laravel\travel;

beforeEach(function () {
    $this->testHandler = new TestHandler;
    Cache::store('array')->clear();
});

test('deduplication respects time window', function () {
    $handler = new DeduplicationHandler(
        handler: $this->testHandler,
        signatureGenerator: new DefaultSignatureGenerator,
        store: 'array',
        ttl: 1
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
    $handler = new DeduplicationHandler(
        handler: $this->testHandler,
        signatureGenerator: new DefaultSignatureGenerator,
        store: 'array'
    );

    $record = createLogRecord();

    $handler->handle($record);
    $handler->handle($record);
    $handler->flush();
    expect($this->testHandler->getRecords())->toHaveCount(1);
});

test('different messages create different signatures', function () {
    $handler = new DeduplicationHandler(
        handler: $this->testHandler,
        signatureGenerator: new DefaultSignatureGenerator,
        store: 'array'
    );

    $record1 = createLogRecord('First message');
    $record2 = createLogRecord('Second message');

    $handler->handle($record1);
    $handler->handle($record2);
    $handler->flush();
    expect($this->testHandler->getRecords())->toHaveCount(2);
});
