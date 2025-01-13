<?php

namespace Naoray\LaravelGithubMonolog\Deduplication\Stores;

use Illuminate\Support\Facades\Redis;
use Monolog\LogRecord;

class RedisStore extends AbstractStore
{
    private string $connection;

    public function __construct(
        string $connection = 'default',
        string $prefix = 'github-monolog:',
        int $time = 60
    ) {
        parent::__construct($prefix, $time);
        $this->connection = $connection;
    }

    private function redis()
    {
        return Redis::connection($this->connection === 'default' ? null : $this->connection);
    }

    // Key Management
    public function getKey(): string
    {
        return $this->prefix . 'dedup';
    }

    // Storage Operations
    public function add(LogRecord $record, string $signature): void
    {
        $this->redis()->zadd($this->getKey(), [
            $signature => time(),
        ]);
    }

    public function get(): array
    {
        $entries = $this->redis()->zrangebyscore(
            $this->getKey(),
            time() - $this->time,
            '+inf',
            ['withscores' => true]
        );

        return array_map(
            fn($entry, $score) => $this->buildEntry($entry, (int) $score),
            array_keys($entries),
            array_values($entries)
        );
    }

    public function cleanup(): void
    {
        $this->redis()->zremrangebyscore($this->getKey(), '-inf', time() - $this->time);
    }
}
