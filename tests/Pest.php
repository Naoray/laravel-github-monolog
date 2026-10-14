<?php

use Illuminate\Support\Arr;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function createLogRecord(
    string $message = 'Test',
    array $context = [],
    array $extra = [],
    Level $level = Level::Error,
    ?Throwable $exception = null,
    ?string $signature = null,
): LogRecord {
    $context = Arr::has($context, 'exception') ? $context : array_merge($context, ['exception' => $exception]);

    return new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'test',
        level: $level,
        message: $message,
        context: $context,
        extra: array_merge($extra, ['github_issue_signature' => $signature]),
    );
}

function generateTestPrivateKey(): string
{
    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($resource, $privateKey);

    return $privateKey;
}
