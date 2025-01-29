<?php

use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function createLogRecord(string $message = 'Test', array $context = [], Level $level = Level::Error): LogRecord
{
    return new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'test',
        level: $level,
        message: $message,
        context: $context,
        extra: [],
    );
}
