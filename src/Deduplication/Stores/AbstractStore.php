<?php

namespace Naoray\LaravelGithubMonolog\DeduplicationStores;

abstract class AbstractDeduplicationStore implements DeduplicationStoreInterface
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
        return $timestamp.':'.$signature;
    }

    protected function isExpired(int $timestamp): bool
    {
        return $timestamp < time() - $this->time;
    }
}
