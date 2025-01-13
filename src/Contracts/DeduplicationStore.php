<?php

namespace Naoray\LaravelGithubMonolog\DeduplicationStores;

use Monolog\LogRecord;

interface DeduplicationStoreInterface
{
    /**
     * Get all stored deduplication entries
     *
     * @return array<string>
     */
    public function get(): array;

    /**
     * Add a new deduplication entry
     */
    public function add(LogRecord $record, string $signature): void;
}
