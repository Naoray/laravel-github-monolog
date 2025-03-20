<?php

use Monolog\Level;
use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;

beforeEach(function () {
    $this->generator = new DefaultSignatureGenerator;
});

test('generates signature from message', function () {
    $record = createLogRecord('Test message', ['foo' => 'bar']);

    $signature1 = $this->generator->generate($record);
    expect($signature1)->toBeString();

    // Same message and context should generate same signature
    $record2 = createLogRecord('Test message', ['foo' => 'bar'], level: Level::Warning);
    $signature2 = $this->generator->generate($record2);
    expect($signature2)->toBe($signature1);

    // Different message should generate different signature
    $record3 = createLogRecord('Different message', ['foo' => 'bar']);
    $signature3 = $this->generator->generate($record3);
    expect($signature3)->not->toBe($signature1);
});

test('generates signature from exception', function () {
    $exception = new \Exception('Test exception');
    $record = createLogRecord('Test message', exception: $exception);

    $signature1 = $this->generator->generate($record);
    expect($signature1)->toBeString();

    // Same exception should generate same signature
    $record2 = createLogRecord('Different message', exception: $exception, level: Level::Warning);
    $signature2 = $this->generator->generate($record2);
    expect($signature2)->toBe($signature1);

    // Different exception should generate different signature
    $differentException = new \Exception('Different exception');
    $record3 = createLogRecord('Test message', exception: $differentException);
    $signature3 = $this->generator->generate($record3);
    expect($signature3)->not->toBe($signature1);
});
