<?php

namespace Naoray\LaravelGithubMonolog\Deduplication\Stores;

use Illuminate\Support\Facades\Redis;
use Monolog\LogRecord;

class RedisStore extends AbstractStore
{
    private string $connection;

    private string $prefix;

    public function __construct(
        string $connection = 'default',
        string $prefix = 'github-monolog:',
        int $time = 60
    ) {
        parent::__construct($time);
        $this->connection = $connection;
        $this->prefix = $prefix;
    }

    private function redis()
    {
        return Redis::connection($this->connection === 'default' ? null : $this->connection);
    }

    // Key Management
    public function getKey(): string
    {
        return $this->prefix.'dedup';
    }

    // Storage Operations
    public function add(LogRecord $record, string $signature): void
    {
        $this->redis()->zadd($this->getKey(), [
            $signature => $this->getTimestamp(),
        ]);
    }

    public function get(): array
    {
        $entries = $this->redis()->zrangebyscore(
            $this->getKey(),
            $this->getTimestampValidity(),
            '+inf',
            ['withscores' => true]
        );

        return array_map(
            fn ($entry, $score) => $this->buildEntry($entry, (int) $score),
            array_keys($entries),
            array_values($entries)
        );
    }

    public function cleanup(): void
    {
        $this->redis()->zremrangebyscore($this->getKey(), '-inf', $this->getTimestampValidity());
    }
}
