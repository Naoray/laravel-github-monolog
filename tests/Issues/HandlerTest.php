<?php

namespace Naoray\LaravelGithubMonolog\Tests;

use Monolog\Level;
use Monolog\Logger;
use Naoray\LaravelGithubMonolog\DeduplicationStores\DatabaseDeduplicationStore;
use Naoray\LaravelGithubMonolog\DeduplicationStores\RedisDeduplicationStore;
use Naoray\LaravelGithubMonolog\Formatters\GithubIssueFormatter;
use Naoray\LaravelGithubMonolog\GithubIssueHandlerFactory;
use Naoray\LaravelGithubMonolog\Issues\Handler;
use Naoray\LaravelGithubMonolog\Handlers\SignatureDeduplicationHandler;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

class GithubIssueHandlerFactoryTest extends TestCase
{
    private array $config = [
        'repo' => 'test/repo',
        'token' => 'test-token',
    ];

    #[Test]
    public function it_creates_logger_with_correct_configuration(): void
    {
        $factory = new GithubIssueHandlerFactory;
        $logger = $factory($this->config);

        expect($logger)
            ->toBeInstanceOf(Logger::class)
            ->and($logger->getName())->toBe('github')
            ->and($logger->getHandlers()[0])
            ->toBeInstanceOf(SignatureDeduplicationHandler::class);

        /** @var SignatureDeduplicationHandler $deduplicationHandler */
        $deduplicationHandler = $logger->getHandlers()[0];
        $handler = $this->getWrappedHandler($deduplicationHandler);

        expect($handler)
            ->toBeInstanceOf(Handler::class)
            ->and($handler->getFormatter())
            ->toBeInstanceOf(GithubIssueFormatter::class);
    }

    #[Test]
    public function it_accepts_custom_log_level(): void
    {
        $factory = new GithubIssueHandlerFactory;
        $logger = $factory([...$this->config, 'level' => Level::Info]);

        /** @var SignatureDeduplicationHandler $handler */
        $handler = $logger->getHandlers()[0];
        expect($handler->getLevel())->toBe(Level::Info);
    }

    #[Test]
    public function it_allows_custom_deduplication_configuration(): void
    {
        $factory = new GithubIssueHandlerFactory;
        $logger = $factory([
            ...$this->config,
            'deduplication' => [
                'driver' => 'database',
                'table' => 'custom_dedup',
                'time' => 300,
            ],
        ]);

        /** @var SignatureDeduplicationHandler $handler */
        $handler = $logger->getHandlers()[0];
        $store = $this->getDeduplicationStore($handler);

        expect($store)->toBeInstanceOf(DatabaseDeduplicationStore::class);
    }

    #[Test]
    public function it_uses_default_values_for_optional_config(): void
    {
        $factory = new GithubIssueHandlerFactory;
        $logger = $factory($this->config);

        /** @var SignatureDeduplicationHandler $handler */
        $handler = $logger->getHandlers()[0];
        $store = $this->getDeduplicationStore($handler);

        expect($store)->toBeInstanceOf(RedisDeduplicationStore::class);
    }

    #[Test]
    public function it_throws_exception_for_missing_required_config(): void
    {
        $factory = new GithubIssueHandlerFactory;

        expect(fn() => $factory([]))->toThrow(\InvalidArgumentException::class);
        expect(fn() => $factory(['repo' => 'test/repo']))->toThrow(\InvalidArgumentException::class);
        expect(fn() => $factory(['token' => 'test-token']))->toThrow(\InvalidArgumentException::class);
    }

    private function getWrappedHandler(SignatureDeduplicationHandler $handler): Handler
    {
        $reflection = new ReflectionProperty($handler, 'handler');
        return $reflection->getValue($handler);
    }

    private function getDeduplicationStore(SignatureDeduplicationHandler $handler): mixed
    {
        $reflection = new ReflectionProperty($handler, 'store');
        return $reflection->getValue($handler);
    }
}
