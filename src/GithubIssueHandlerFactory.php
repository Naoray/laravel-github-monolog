<?php

namespace Naoray\LaravelGithubMonolog;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Monolog\Level;
use Monolog\Logger;
use Naoray\LaravelGithubMonolog\Auth\GithubAppTokenProvider;
use Naoray\LaravelGithubMonolog\Auth\PersonalAccessTokenProvider;
use Naoray\LaravelGithubMonolog\Auth\TokenProviderInterface;
use Naoray\LaravelGithubMonolog\Deduplication\DeduplicationHandler;
use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;
use Naoray\LaravelGithubMonolog\Deduplication\SignatureGeneratorInterface;
use Naoray\LaravelGithubMonolog\Issues\Formatters\IssueFormatter;
use Naoray\LaravelGithubMonolog\Issues\Handler;
use Naoray\LaravelGithubMonolog\Tracing\CallerFrameProcessor;
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
        $logger->pushProcessor(new CallerFrameProcessor);
        $logger->pushProcessor(new ContextProcessor);

        return $logger;
    }

    protected function validateConfig(array $config): void
    {
        if (! Arr::has($config, 'repo')) {
            throw new InvalidArgumentException('GitHub repository is required');
        }

        if (blank(Arr::get($config, 'token')) && ! $this->hasGithubAppConfig($config)) {
            throw new InvalidArgumentException(
                'A GitHub token or GitHub App credentials (github_app.id, github_app.installation_id and github_app.private_key or github_app.private_key_path) are required'
            );
        }
    }

    protected function hasGithubAppConfig(array $config): bool
    {
        return filled(Arr::get($config, 'github_app.id'))
            && filled(Arr::get($config, 'github_app.installation_id'))
            && (filled(Arr::get($config, 'github_app.private_key')) || filled(Arr::get($config, 'github_app.private_key_path')));
    }

    protected function createBaseHandler(array $config): Handler
    {
        $handler = new Handler(
            repo: $config['repo'],
            token: $this->resolveTokenProvider($config)->getToken(),
            labels: Arr::get($config, 'labels', []),
            level: Arr::get($config, 'level', Level::Error),
            bubble: Arr::get($config, 'bubble', true)
        );

        $handler->setFormatter($this->formatter);

        return $handler;
    }

    protected function resolveTokenProvider(array $config): TokenProviderInterface
    {
        if ($this->hasGithubAppConfig($config)) {
            return new GithubAppTokenProvider(
                appId: (string) $config['github_app']['id'],
                installationId: (string) $config['github_app']['installation_id'],
                privateKey: $this->resolvePrivateKey($config['github_app']),
                cacheStore: Arr::get($config, 'deduplication.store', config('cache.default')),
                cachePrefix: Arr::get($config, 'deduplication.prefix', 'github-monolog:'),
            );
        }

        return new PersonalAccessTokenProvider($config['token']);
    }

    /**
     * Resolve the GitHub App private key. Accepts either the raw PEM
     * contents (optionally base64-encoded, which is friendlier to store in
     * a single .env value) or a path to a PEM file on disk.
     */
    protected function resolvePrivateKey(array $githubAppConfig): string
    {
        $privateKey = Arr::get($githubAppConfig, 'private_key');

        if (filled($privateKey)) {
            if (str_contains($privateKey, 'BEGIN')) {
                return $privateKey;
            }

            $decoded = base64_decode($privateKey, true);

            return $decoded !== false && str_contains($decoded, 'BEGIN') ? $decoded : $privateKey;
        }

        $path = Arr::get($githubAppConfig, 'private_key_path');

        if (! is_string($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("GitHub App private key file [{$path}] is not readable");
        }

        return file_get_contents($path);
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
            flushOnOverflow: Arr::get($config, 'buffer.flush_on_overflow', true),
            trackOccurrences: Arr::get($deduplication, 'track_occurrences', true),
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
