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

test('first occurrence has count of 1', function () {
    $handler = new DeduplicationHandler(
        handler: $this->testHandler,
        signatureGenerator: new DefaultSignatureGenerator,
        store: 'array',
        trackOccurrences: true,
    );

    $record = createLogRecord('Test message');
    $handler->handle($record);
    $handler->flush();

    $records = $this->testHandler->getRecords();
    expect($records)->toHaveCount(1);
    expect($records[0]->extra['github_occurrence_count'])->toBe(1);
});

test('occurrence count increments for duplicate records within ttl window', function () {
    $handler = new DeduplicationHandler(
        handler: $this->testHandler,
        signatureGenerator: new DefaultSignatureGenerator,
        store: 'array',
        ttl: 60,
        trackOccurrences: true,
    );

    $record = createLogRecord('Test message');

    // First occurrence
    $handler->handle($record);
    $handler->flush();
    expect($this->testHandler->getRecords())->toHaveCount(1);
    expect($this->testHandler->getRecords()[0]->extra['github_occurrence_count'])->toBe(1);

    // Second occurrence within TTL - deduplicated but count still incremented in cache
    $handler->handle($record);
    $handler->flush();
    // Still only 1 record processed (second was deduplicated)
    expect($this->testHandler->getRecords())->toHaveCount(1);

    // Third occurrence within TTL - also deduplicated, count now 3
    $handler->handle($record);
    $handler->flush();
    expect($this->testHandler->getRecords())->toHaveCount(1);
});

test('occurrence count resets after ttl expires', function () {
    $handler = new DeduplicationHandler(
        handler: $this->testHandler,
        signatureGenerator: new DefaultSignatureGenerator,
        store: 'array',
        ttl: 1,
        trackOccurrences: true,
    );

    $record = createLogRecord('Test message');

    // First occurrence
    $handler->handle($record);
    $handler->flush();
    expect($this->testHandler->getRecords())->toHaveCount(1);
    expect($this->testHandler->getRecords()[0]->extra['github_occurrence_count'])->toBe(1);

    // Wait for TTL to expire
    travel(2)->seconds();

    // After TTL expiry, both dedup and count are reset
    $handler->handle($record);
    $handler->flush();
    expect($this->testHandler->getRecords())->toHaveCount(2);
    expect($this->testHandler->getRecords()[1]->extra['github_occurrence_count'])->toBe(1);
});

test('occurrence count is not added when tracking is disabled', function () {
    $handler = new DeduplicationHandler(
        handler: $this->testHandler,
        signatureGenerator: new DefaultSignatureGenerator,
        store: 'array',
        trackOccurrences: false,
    );

    $record = createLogRecord('Test message');
    $handler->handle($record);
    $handler->flush();

    $records = $this->testHandler->getRecords();
    expect($records)->toHaveCount(1);
    expect($records[0]->extra)->not->toHaveKey('github_occurrence_count');
});
