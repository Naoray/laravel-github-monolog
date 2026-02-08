<?php

namespace Naoray\LaravelGithubMonolog\Deduplication;

use Illuminate\Support\Collection;
use Monolog\Handler\BufferHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;

class DeduplicationHandler extends BufferHandler
{
    private CacheManager $cache;

    public function __construct(
        HandlerInterface $handler,
        protected SignatureGeneratorInterface $signatureGenerator,
        ?string $store = 'default',
        string $prefix = 'github-monolog:dedup:',
        int $ttl = 60,
        int|string|Level $level = Level::Error,
        int $bufferLimit = 0,
        bool $bubble = true,
        bool $flushOnOverflow = false,
        private bool $trackOccurrences = true,
    ) {
        parent::__construct(
            handler: $handler,
            bufferLimit: $bufferLimit,
            level: $level,
            bubble: $bubble,
            flushOnOverflow: $flushOnOverflow,
        );

        $this->cache = new CacheManager($store, $prefix, $ttl);
    }

    public function flush(): void
    {
        if ($this->bufferSize === 0) {
            return;
        }

        collect($this->buffer)
            ->map(function (LogRecord $record) {
                $signature = $this->signatureGenerator->generate($record);

                $extra = ['github_issue_signature' => $signature] + $record->extra;

                // Track occurrence count when enabled
                if ($this->trackOccurrences) {
                    $occurrenceCount = $this->cache->incrementOccurrenceCount($signature);
                    $extra['github_occurrence_count'] = $occurrenceCount;
                }

                // Create new record with signature and occurrence data in extra
                $record = $record->with(extra: $extra);

                // If the record is a duplicate, we don't want to process it
                if ($this->cache->has($signature)) {
                    return null;
                }

                $this->cache->add($signature);

                return $record;
            })
            ->filter()
            ->pipe(fn (Collection $records) => $this->handler->handleBatch($records->toArray()));

        $this->clear();
    }
}
