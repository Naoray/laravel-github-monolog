<?php

namespace Naoray\LaravelGithubMonolog\Deduplication\Stores;

use Illuminate\Support\Carbon;
use Monolog\LogRecord;

abstract class AbstractStore implements StoreInterface
{
    public function __construct(
        protected int $time = 60
    ) {}

    protected function buildEntry(string $signature, int $timestamp): string
    {
        return $timestamp.':'.$signature;
    }

    public function isDuplicate(LogRecord $record, string $signature): bool
    {
        $foundDuplicate = false;

        foreach ($this->get() as $entry) {
            [$timestamp, $storedSignature] = explode(':', $entry, 2);
            $timestamp = (int) $timestamp;

            if ($this->isExpired($timestamp)) {
                continue;
            }

            if ($storedSignature === $signature) {
                $foundDuplicate = true;
            }
        }

        return $foundDuplicate;
    }

    protected function isExpired(int $timestamp): bool
    {
        return $this->getTimestampValidity() > $timestamp;
    }

    protected function getTimestampValidity(): int
    {
        return $this->getTimestamp() - $this->time;
    }

    protected function getTimestamp(): int
    {
        return Carbon::now()->timestamp;
    }
}
