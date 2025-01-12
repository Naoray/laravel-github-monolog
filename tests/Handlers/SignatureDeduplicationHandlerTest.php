<?php

use Monolog\Handler\NullHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\DefaultSignatureGenerator;
use Naoray\LaravelGithubMonolog\Handlers\SignatureDeduplicationHandler;

beforeEach(function () {
    $this->deduplicationStore = sys_get_temp_dir().'/test-dedup-'.uniqid().'.log';
    $this->signatureGenerator = new DefaultSignatureGenerator;
    $this->handler = new SignatureDeduplicationHandler(
        new NullHandler,
        $this->deduplicationStore,
        Level::Debug,
        time: 60,
        signatureGenerator: $this->signatureGenerator
    );
});

afterEach(function () {
    if (file_exists($this->deduplicationStore)) {
        unlink($this->deduplicationStore);
    }
});

test('deduplicates records with same signature', function () {
    $record1 = new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: 'Test message',
        context: ['foo' => 'bar'],
        extra: [],
    );

    // First record should be handled
    $this->handler->handle($record1);
    $this->handler->flush();
    expect(file_exists($this->deduplicationStore))->toBeTrue();

    // Same signature should be deduplicated
    $record2 = new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'different-channel',
        level: Level::Error,
        message: 'Test message',
        context: ['foo' => 'bar'],
        extra: ['something' => 'else'],
    );
    $this->handler->handle($record2);
    $this->handler->flush();

    // Different signature should not be deduplicated
    $record3 = new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: 'Different message',
        context: ['foo' => 'bar'],
        extra: [],
    );
    $this->handler->handle($record3);

    // Verify deduplication store contains both unique signatures
    $this->handler->close();
    $contents = file_get_contents($this->deduplicationStore);
    expect($contents)
        ->toContain($this->signatureGenerator->generate($record1))
        ->toContain($this->signatureGenerator->generate($record3));
});

test('deduplication respects time window', function () {
    $record = new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: 'Test message',
        context: ['foo' => 'bar'],
        extra: [],
    );

    // Create handler with 1 second time window
    $handler = new SignatureDeduplicationHandler(
        new NullHandler,
        $this->deduplicationStore,
        Level::Debug,
        time: 1,
        signatureGenerator: $this->signatureGenerator
    );

    // First record should be handled
    $handler->handle($record);
    $handler->flush();
    expect(file_exists($this->deduplicationStore))->toBeTrue();

    // Wait for time window to expire
    sleep(2);

    // Same record should be handled again after time window
    $handler->handle($record);
    $handler->flush();
    $handler->close();

    // Verify deduplication store contains only the most recent entry
    $contents = file_get_contents($this->deduplicationStore);
    $signature = $this->signatureGenerator->generate($record);
    expect(substr_count($contents, $signature))->toBe(1);
});
