<?php

namespace Naoray\LaravelGithubMonolog;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Monolog\Level;
use Monolog\Logger;
use Naoray\LaravelGithubMonolog\DeduplicationStores\DatabaseDeduplicationStore;
use Naoray\LaravelGithubMonolog\DeduplicationStores\DeduplicationStoreInterface;
use Naoray\LaravelGithubMonolog\DeduplicationStores\RedisDeduplicationStore;
use Naoray\LaravelGithubMonolog\DeduplicationStores\FileDeduplicationStore;
use Naoray\LaravelGithubMonolog\Formatters\GithubIssueFormatter;
use Naoray\LaravelGithubMonolog\Issues\Handler;
use Naoray\LaravelGithubMonolog\Handlers\SignatureDeduplicationHandler;

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
            labels: $config['labels'] ?? [],
            level: Arr::get($config, 'level', Level::Error),
            bubble: Arr::get($config, 'bubble', true)
        );

        $handler->setFormatter(new GithubIssueFormatter);

        return $handler;
    }

    protected function wrapWithDeduplication(Handler $handler, array $config): SignatureDeduplicationHandler
    {
        return new SignatureDeduplicationHandler(
            $handler,
            store: $this->createDeduplicationStore($config),
            level: Arr::get($config, 'level', Level::Error),
            time: $this->getDeduplicationTime($config),
            bubble: true
        );
    }

    protected function createDeduplicationStore(array $config): DeduplicationStoreInterface
    {
        $deduplication = Arr::get($config, 'deduplication', []);
        $driver = Arr::get($deduplication, 'driver', 'redis');
        $time = $this->getDeduplicationTime($config);
        $prefix = Arr::get($deduplication, 'prefix', 'github-monolog:');
        $connection = Arr::get($deduplication, 'connection', 'default');

        return match ($driver) {
            'redis' => new RedisDeduplicationStore(
                connection: $connection,
                prefix: $prefix,
                time: $time
            ),
            'database' => new DatabaseDeduplicationStore(
                connection: $connection,
                table: Arr::get($deduplication, 'table', 'github_monolog_deduplication'),
                prefix: $prefix,
                time: $time
            ),
            'file' => new FileDeduplicationStore(
                path: Arr::get($deduplication, 'path', storage_path('logs/github-monolog/deduplication.log')),
                prefix: $prefix,
                time: $time
            ),
            default => throw new InvalidArgumentException("Unsupported deduplication driver: {$driver}")
        };
    }

    protected function getDeduplicationTime(array $config): int
    {
        return (int) Arr::get($config, 'deduplication.time', 300); // Default to 5 minutes
    }
}
