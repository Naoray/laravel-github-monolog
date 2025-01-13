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
        protected int $time = 60,
        bool $bubble = true,
    ) {
        parent::__construct(
            handler: $handler,
            bufferLimit: 0,
            level: $level,
            bubble: $bubble,
            flushOnOverflow: false,
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

                if ($this->store->isDuplicate($record, $signature)) {
                    $this->store->add($record, $signature);

                    return null;
                }

                return $record;
            })
            ->filter()
            ->pipe(fn(Collection $records) => $this->handler->handleBatch($records->toArray()));

        $this->clear();
    }
}
