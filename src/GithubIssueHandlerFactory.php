<?php

namespace Naoray\LaravelGithubMonolog;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Monolog\Level;
use Monolog\Logger;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\DatabaseStore;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\FileStore;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\RedisStore;
use Naoray\LaravelGithubMonolog\Issues\Formatter;
use Naoray\LaravelGithubMonolog\Deduplication\DeduplicationHandler;
use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\StoreInterface;
use Naoray\LaravelGithubMonolog\Issues\Handler;

class GithubIssueHandlerFactory
{
    public function __invoke(array $config): Logger
    {
        $this->validateConfig($config);

        $handler = $this->createBaseHandler($config);
        $deduplicationHandler = $this->wrapWithDeduplication($handler, $config);

        return new Logger('github', [$deduplicationHandler]);
    }

    protected function validateConfig(array $config): void
    {
        if (! Arr::has($config, 'repo')) {
            throw new InvalidArgumentException('GitHub repository is required');
        }

        if (! Arr::has($config, 'token')) {
            throw new InvalidArgumentException('GitHub token is required');
        }
    }

    protected function createBaseHandler(array $config): Handler
    {
        $handler = new Handler(
            repo: $config['repo'],
            token: $config['token'],
            signatureGenerator: new DefaultSignatureGenerator,
            labels: Arr::get($config, 'labels', []),
            level: Arr::get($config, 'level', Level::Error),
            bubble: Arr::get($config, 'bubble', true)
        );

        $handler->setFormatter(new Formatter(new DefaultSignatureGenerator));

        return $handler;
    }

    protected function wrapWithDeduplication(Handler $handler, array $config): DeduplicationHandler
    {
        return new DeduplicationHandler(
            handler: $handler,
            store: $this->createDeduplicationStore($config),
            signatureGenerator: new DefaultSignatureGenerator,
            level: Arr::get($config, 'level', Level::Error),
            bubble: true,
            bufferLimit: Arr::get($config, 'buffer.limit', 0),
            flushOnOverflow: Arr::get($config, 'buffer.flushOnOverflow', true)
        );
    }

    protected function createDeduplicationStore(array $config): StoreInterface
    {
        $deduplication = Arr::get($config, 'deduplication', []);
        $driver = Arr::get($deduplication, 'driver', 'redis');
        $time = $this->getDeduplicationTime($config);
        $prefix = Arr::get($deduplication, 'prefix', 'github-monolog:');
        $connection = Arr::get($deduplication, 'connection', 'default');

        return match ($driver) {
            'redis' => new RedisStore(
                connection: $connection,
                prefix: $prefix,
                time: $time
            ),
            'database' => new DatabaseStore(
                connection: $connection,
                table: Arr::get($deduplication, 'table', 'github_monolog_deduplication'),
                prefix: $prefix,
                time: $time
            ),
            default => new FileStore(
                path: Arr::get($deduplication, 'path', storage_path('logs/github-monolog/deduplication.log')),
                prefix: $prefix,
                time: $time
            )
        };
    }

    protected function getDeduplicationTime(array $config): int
    {
        return (int) Arr::get($config, 'deduplication.time', 300); // Default to 5 minutes
    }
}
