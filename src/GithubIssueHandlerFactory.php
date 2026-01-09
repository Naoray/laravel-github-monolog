<?php

namespace Naoray\LaravelGithubMonolog;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Monolog\Level;
use Monolog\Logger;
use Naoray\LaravelGithubMonolog\Deduplication\DeduplicationHandler;
use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;
use Naoray\LaravelGithubMonolog\Deduplication\SignatureGeneratorInterface;
use Naoray\LaravelGithubMonolog\Issues\Formatters\IssueFormatter;
use Naoray\LaravelGithubMonolog\Issues\Handler;
use Naoray\LaravelGithubMonolog\Tracing\ContextProcessor;

class GithubIssueHandlerFactory
{
    public function __construct(
        private readonly IssueFormatter $formatter,
    ) {}

    public function __invoke(array $config): Logger
    {
        $this->validateConfig($config);

        $handler = $this->createBaseHandler($config);
        $deduplicationHandler = $this->wrapWithDeduplication($handler, $config);

        $logger = new Logger('github', [$deduplicationHandler]);
        $logger->pushProcessor(new ContextProcessor);

        return $logger;
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
            labels: Arr::get($config, 'labels', []),
            level: Arr::get($config, 'level', Level::Error),
            bubble: Arr::get($config, 'bubble', true)
        );

        $handler->setFormatter($this->formatter);

        return $handler;
    }

    protected function wrapWithDeduplication(Handler $handler, array $config): DeduplicationHandler
    {
        $signatureGeneratorClass = Arr::get($config, 'signature_generator', DefaultSignatureGenerator::class);

        if (! is_subclass_of($signatureGeneratorClass, SignatureGeneratorInterface::class)) {
            throw new InvalidArgumentException(
                sprintf('Signature generator class [%s] must implement %s', $signatureGeneratorClass, SignatureGeneratorInterface::class)
            );
        }

        /** @var SignatureGeneratorInterface $signatureGenerator */
        $signatureGenerator = new $signatureGeneratorClass;

        $deduplication = Arr::get($config, 'deduplication', []);

        return new DeduplicationHandler(
            handler: $handler,
            signatureGenerator: $signatureGenerator,
            store: Arr::get($deduplication, 'store', config('cache.default')),
            prefix: Arr::get($deduplication, 'prefix', 'github-monolog:'),
            ttl: $this->getDeduplicationTime($config),
            level: Arr::get($config, 'level', Level::Error),
            bufferLimit: Arr::get($config, 'buffer.limit', 0),
            flushOnOverflow: Arr::get($config, 'buffer.flush_on_overflow', true)
        );
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
