<?php

namespace Naoray\LaravelGithubMonolog\Deduplication;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class CacheManager
{
    private const KEY_PREFIX = 'github-monolog';

    private const KEY_SEPARATOR = ':';

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
     * Clear all entries for the current prefix.
     * Note: This is a best-effort operation and might not work with all cache stores.
     */
    public function clear(): void
    {
        // All Laravel cache stores implement the flush method
        $this->cache->getStore()->flush();
    }

    private function composeKey(string $signature): string
    {
        return implode(self::KEY_SEPARATOR, [
            self::KEY_PREFIX,
            $this->prefix,
            $signature,
        ]);
    }
}
