<?php

namespace Naoray\LaravelGithubMonolog\Deduplication;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class CacheManager
{
    private const KEY_PREFIX = 'github-monolog';

    private const KEY_SEPARATOR = ':';

    private const OCCURRENCE_KEY_SEGMENT = 'count';

    private readonly string $store;

    private readonly Repository $cache;

    public function __construct(
        ?string $store = null,
        private readonly string $prefix = 'dedup',
        private readonly int $ttl = 60
    ) {
        $this->store = $store ?? config('cache.default');
        $this->cache = Cache::store($this->store);
    }

    public function has(string $signature): bool
    {
        return $this->cache->has($this->composeKey($signature));
    }

    public function add(string $signature): void
    {
        $this->cache->put(
            $this->composeKey($signature),
            Carbon::now()->timestamp,
            $this->ttl
        );
    }

    /**
     * Increment and return the occurrence count for the given signature.
     *
     * The occurrence counter uses a separate cache key from the deduplication
     * signature so it can persist independently. The counter never expires on
     * its own -- it shares the same TTL as the deduplication entry but is
     * refreshed every time the signature is seen.
     */
    public function incrementOccurrenceCount(string $signature): int
    {
        $key = $this->composeOccurrenceKey($signature);
        $count = (int) $this->cache->get($key, 0) + 1;
        $this->cache->put($key, $count, $this->ttl);

        return $count;
    }

    /**
     * Get the current occurrence count for the given signature.
     */
    public function getOccurrenceCount(string $signature): int
    {
        return (int) $this->cache->get($this->composeOccurrenceKey($signature), 0);
    }

    private function composeKey(string $signature): string
    {
        return implode(self::KEY_SEPARATOR, [
            self::KEY_PREFIX,
            $this->prefix,
            $signature,
        ]);
    }

    private function composeOccurrenceKey(string $signature): string
    {
        return implode(self::KEY_SEPARATOR, [
            self::KEY_PREFIX,
            $this->prefix,
            self::OCCURRENCE_KEY_SEGMENT,
            $signature,
        ]);
    }
}
