<?php

namespace Naoray\LaravelGithubMonolog\Deduplication\Stores;

use Monolog\LogRecord;

abstract class AbstractStore implements StoreInterface
{
    public function __construct(
        protected string $prefix = 'github-monolog:',
        protected int $time = 60
    ) {}

    protected function buildEntry(string $signature, int $timestamp): string
    {
        return $timestamp . ':' . $signature;
    }

    public function isDuplicate(LogRecord $record, string $signature): bool
    {
        $timestampValidity = time() - $this->time;
        $foundDuplicate = false;

        foreach ($this->get() as $entry) {
            [$timestamp, $storedSignature] = explode(':', $entry, 2);
            $timestamp = (int) $timestamp;

            if ($timestamp <= $timestampValidity) {
                continue;
            }

            if ($storedSignature === $signature) {
                $foundDuplicate = true;
            }
        }

        return $foundDuplicate;
    }
}
