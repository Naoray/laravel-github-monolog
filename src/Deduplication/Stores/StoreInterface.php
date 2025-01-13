<?php

namespace Naoray\LaravelGithubMonolog\Deduplication\Stores;

use Monolog\LogRecord;

interface StoreInterface
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

    /**
     * Check if a record with the given signature is a duplicate
     */
    public function isDuplicate(LogRecord $record, string $signature): bool;

    /**
     * Clean up expired entries
     */
    public function cleanup(): void;
}
