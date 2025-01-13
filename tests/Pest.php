<?php

use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Tests\TestCase;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\DatabaseStore;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\FileStore;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\RedisStore;

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

function createDatabaseStore(string $prefix = 'test:', int $time = 60): DatabaseStore
{
    return new DatabaseStore(
        connection: 'sqlite',
        table: 'github_monolog_deduplication',
        prefix: $prefix,
        time: $time
    );
}

function createFileStore(string $prefix = 'test:', int $time = 60): FileStore
{
    return new FileStore(
        path: test()->testPath,
        prefix: $prefix,
        time: $time
    );
}

function createRedisStore(string $prefix = 'test:', int $time = 60): RedisStore
{
    return new RedisStore(
        connection: 'default',
        prefix: $prefix,
        time: $time
    );
}
