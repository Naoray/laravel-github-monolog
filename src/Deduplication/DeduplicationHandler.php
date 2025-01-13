<?php

namespace Naoray\LaravelGithubMonolog\Deduplication;

use Illuminate\Support\Collection;
use Monolog\Handler\BufferHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Deduplication\Stores\StoreInterface;

class DeduplicationHandler extends BufferHandler
{
    public function __construct(
        HandlerInterface $handler,
        protected StoreInterface $store,
        protected SignatureGeneratorInterface $signatureGenerator,
        int|string|Level $level = Level::Error,
        int $bufferLimit = 0,
        bool $bubble = true,
        bool $flushOnOverflow = false,
    ) {
        parent::__construct(
            handler: $handler,
            bufferLimit: $bufferLimit,
            level: $level,
            bubble: $bubble,
            flushOnOverflow: $flushOnOverflow,
        );
    }

    public function flush(): void
    {
        if ($this->bufferSize === 0) {
            return;
        }

        collect($this->buffer)
            ->map(function (LogRecord $record) {
                $signature = $this->signatureGenerator->generate($record);

                // Create new record with signature in extra data
                $record = $record->with(extra: ['github_issue_signature' => $signature] + $record->extra);

                // If the record is a duplicate, we don't want to add it to the store
                if ($this->store->isDuplicate($record, $signature)) {
                    return null;
                }

                $this->store->add($record, $signature);

                return $record;
            })
            ->filter()
            ->pipe(fn(Collection $records) => $this->handler->handleBatch($records->toArray()));

        $this->clear();
    }
}
