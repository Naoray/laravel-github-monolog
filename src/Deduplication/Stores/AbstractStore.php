<?php

namespace Naoray\LaravelGithubMonolog\Deduplication\Stores;

use Monolog\LogRecord;

abstract class AbstractStore implements StoreInterface
{
    protected string $prefix;

    protected int $time;

    public function __construct(string $prefix = 'github-monolog:', int $time = 60)
    {
        $this->prefix = $prefix;
        $this->time = $time;
    }

    protected function formatEntry(string $signature, int $timestamp): string
    {
        return $timestamp . ':' . $signature;
    }

    protected function isExpired(int $timestamp): bool
    {
        return $timestamp < time() - $this->time;
    }

    public function isDuplicate(LogRecord $record, string $signature): bool
    {
        $timestampValidity = time() - $this->time;

        foreach ($this->get() as $entry) {
            [$timestamp, $storedSignature] = explode(':', $entry, 2);

            if ($storedSignature === $signature && (int) $timestamp > $timestampValidity) {
                return true;
            }
        }

        return false;
    }
}
