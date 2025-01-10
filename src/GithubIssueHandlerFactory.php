<?php

namespace Naoray\LaravelGithubMonolog;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Monolog\Handler\DeduplicationHandler;
use Monolog\Level;
use Monolog\Logger;

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

    protected function createBaseHandler(array $config): GithubIssueLoggerHandler
    {
        $handler = new GithubIssueLoggerHandler(
            repo: $config['repo'],
            token: $config['token'],
            labels: $config['labels'] ?? [],
            level: Arr::get($config, 'level', Level::Error),
            bubble: Arr::get($config, 'bubble', true)
        );

        $handler->setFormatter(new GithubIssueFormatter);

        return $handler;
    }

    protected function wrapWithDeduplication(GithubIssueLoggerHandler $handler, array $config): DeduplicationHandler
    {
        return new DeduplicationHandler(
            $handler,
            deduplicationStore: $this->getDeduplicationStore($config),
            deduplicationLevel: Arr::get($config, 'level', Level::Error),
            time: $this->getDeduplicationTime($config),
            bubble: true
        );
    }

    protected function getDeduplicationStore(array $config): string
    {
        $deduplication = Arr::get($config, 'deduplication', []);

        if ($store = Arr::get($deduplication, 'store')) {
            return $store;
        }

        $store = storage_path('logs/github-issues-dedup.log');
        File::ensureDirectoryExists(dirname($store));

        return $store;
    }

    protected function getDeduplicationTime(array $config): int
    {
        return (int) Arr::get($config, 'deduplication.time', 60);
    }
}
