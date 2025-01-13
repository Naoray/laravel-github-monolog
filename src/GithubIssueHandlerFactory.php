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
use Naoray\LaravelGithubMonolog\Deduplication\SignatureGeneratorInterface;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\StoreInterface;
use Naoray\LaravelGithubMonolog\Issues\Handler;

class GithubIssueHandlerFactory
{
    public function __construct(
        private readonly SignatureGeneratorInterface $signatureGenerator,
    ) {}

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
            signatureGenerator: $this->signatureGenerator,
            labels: Arr::get($config, 'labels', []),
            level: Arr::get($config, 'level', Level::Error),
            bubble: Arr::get($config, 'bubble', true)
        );

        $handler->setFormatter(new Formatter($this->signatureGenerator));

        return $handler;
    }

    protected function wrapWithDeduplication(Handler $handler, array $config): DeduplicationHandler
    {
        return new DeduplicationHandler(
            handler: $handler,
            store: $this->createDeduplicationStore($config),
            signatureGenerator: $this->signatureGenerator,
            level: Arr::get($config, 'level', Level::Error),
            bubble: true,
            bufferLimit: Arr::get($config, 'buffer.limit', 0),
            flushOnOverflow: Arr::get($config, 'buffer.flushOnOverflow', true)
        );
    }

    protected function createDeduplicationStore(array $config): StoreInterface
    {
        $deduplication = Arr::get($config, 'deduplication', []);
        $driver = Arr::get($deduplication, 'driver', 'file');
        $time = $this->getDeduplicationTime($config);
        $prefix = Arr::get($deduplication, 'prefix', 'github-monolog:');
        $connection = Arr::get($deduplication, 'connection', 'default');

        return match ($driver) {
            'redis' => new RedisStore(prefix: $prefix, time: $time, connection: $connection),
            'database' => new DatabaseStore(prefix: $prefix, time: $time, connection: $connection),
            default => new FileStore(path: Arr::get($deduplication, 'path', storage_path('logs/github-monolog-deduplication.log')), prefix: $prefix, time: $time),
        };
    }

    protected function getDeduplicationTime(array $config): int
    {
        $time = Arr::get($config, 'deduplication.time', 60);

        if (! is_numeric($time) || $time < 0) {
            throw new InvalidArgumentException('Deduplication time must be a positive integer');
        }

        return (int) $time;
    }
}
