<?php

use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;

beforeEach(function () {
    $this->generator = new DefaultSignatureGenerator;
});

test('generates signature from message', function () {
    $record = new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: 'Test message',
        context: ['foo' => 'bar'],
        extra: [],
    );

    $signature1 = $this->generator->generate($record);
    expect($signature1)->toBeString();

    // Same message and context should generate same signature
    $record2 = new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'different-channel',
        level: Level::Warning,
        message: 'Test message',
        context: ['foo' => 'bar'],
        extra: ['something' => 'else'],
    );
    $signature2 = $this->generator->generate($record2);
    expect($signature2)->toBe($signature1);

    // Different message should generate different signature
    $record3 = new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: 'Different message',
        context: ['foo' => 'bar'],
        extra: [],
    );
    $signature3 = $this->generator->generate($record3);
    expect($signature3)->not->toBe($signature1);
});

test('generates signature from exception', function () {
    $exception = new \Exception('Test exception');
    $record = new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: 'Test message',
        context: ['exception' => $exception],
        extra: [],
    );

    $signature1 = $this->generator->generate($record);
    expect($signature1)->toBeString();

    // Same exception should generate same signature
    $record2 = new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'different-channel',
        level: Level::Warning,
        message: 'Different message',
        context: ['exception' => $exception],
        extra: ['something' => 'else'],
    );
    $signature2 = $this->generator->generate($record2);
    expect($signature2)->toBe($signature1);

    // Different exception should generate different signature
    $differentException = new \Exception('Different exception');
    $record3 = new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'test',
        level: Level::Error,
        message: 'Test message',
        context: ['exception' => $differentException],
        extra: [],
    );
    $signature3 = $this->generator->generate($record3);
    expect($signature3)->not->toBe($signature1);
});
