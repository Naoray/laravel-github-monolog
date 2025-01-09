<?php

namespace Naoray\LaravelGithubMonolog;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Monolog\Level;
use Monolog\Logger;

class GithubIssueHandlerFactory
{
    public function __invoke(array $config): Logger
    {
        if (Arr::has($config, ['repo', 'token'])) {
            throw new InvalidArgumentException('GitHub repository and token are required');
        }

        $handler = new GithubIssueLoggerHandler(
            repo: $config['repo'],
            token: $config['token'],
            labels: $config['labels'] ?? [],
            level: Arr::get($config, 'level', Level::Error),
            bubble: Arr::get($config, 'bubble', true)
        );

        $handler->setFormatter(new GithubIssueFormatter());

        return new Logger('github', [$handler]);
    }
}
