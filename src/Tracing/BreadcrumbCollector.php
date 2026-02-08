<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\ResolvesTracingConfig;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\DataCollectorInterface;

class BreadcrumbCollector implements DataCollectorInterface
{
    use ResolvesTracingConfig;

    private const DEFAULT_LIMIT = 40;

    /**
     * @var array<int, array{timestamp: string, category: string, message: string, metadata: array<string, mixed>}>
     */
    private static array $breadcrumbs = [];

    public function isEnabled(): bool
    {
        return $this->isTracingFeatureEnabled('breadcrumbs');
    }

    /**
     * Handle a MessageLogged event.
     */
    public function handleMessageLogged(MessageLogged $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // Skip error and above levels since those trigger the reporter itself
        if (in_array($event->level, ['error', 'critical', 'alert', 'emergency'], true)) {
            return;
        }

        $this->addBreadcrumb('log', "[{$event->level}] {$event->message}");
    }

    /**
     * Handle a CacheHit event.
     */
    public function handleCacheHit(CacheHit $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->addBreadcrumb('cache', "Cache hit: {$event->key}", [
            'store' => $event->storeName ?? 'default',
        ]);
    }

    /**
     * Handle a CacheMissed event.
     */
    public function handleCacheMissed(CacheMissed $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->addBreadcrumb('cache', "Cache miss: {$event->key}", [
            'store' => $event->storeName ?? 'default',
        ]);
    }

    /**
     * Collect breadcrumbs and push them to Context.
     */
    public function collect(): void
    {
        if (empty(self::$breadcrumbs)) {
            return;
        }

        Context::addHidden('breadcrumbs', self::$breadcrumbs);
    }

    /**
     * Add a breadcrumb entry to the ring buffer.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function addBreadcrumb(string $category, string $message, array $metadata = []): void
    {
        $limit = $this->getTracingConfig('breadcrumb_limit') ?? self::DEFAULT_LIMIT;

        self::$breadcrumbs[] = [
            'timestamp' => Carbon::now()->format('H:i:s.v'),
            'category' => $category,
            'message' => $message,
            'metadata' => $metadata,
        ];

        // Ring buffer: keep only the last N entries
        if (count(self::$breadcrumbs) > $limit) {
            self::$breadcrumbs = array_slice(self::$breadcrumbs, -$limit);
        }
    }

    /**
     * Reset the breadcrumbs (primarily for testing).
     */
    public static function reset(): void
    {
        self::$breadcrumbs = [];
    }

    /**
     * Get the current breadcrumbs (primarily for testing).
     *
     * @return array<int, array{timestamp: string, category: string, message: string, metadata: array<string, mixed>}>
     */
    public static function getBreadcrumbs(): array
    {
        return self::$breadcrumbs;
    }
}
