<?php

namespace Naoray\LaravelGithubMonolog\Handlers;

use Illuminate\Support\Collection;
use Monolog\Handler\BufferHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Contracts\SignatureGenerator;
use Naoray\LaravelGithubMonolog\Deduplication\Store\DeduplicationStoreContract;
<<<<<<< HEAD
use Naoray\LaravelGithubMonolog\DeduplicationStores\RedisDeduplicationStore;
use Naoray\LaravelGithubMonolog\DefaultSignatureGenerator;
=======
>>>>>>> 4666cb4 (wip)

class DeduplicationHandler extends BufferHandler
{
<<<<<<< HEAD
    private SignatureGenerator $signatureGenerator;

    private HandlerInterface $handler;

    private DeduplicationStoreInterface $store;

    private int $time;

=======
>>>>>>> 4666cb4 (wip)
    public function __construct(
        HandlerInterface $handler,
        protected DeduplicationStoreInterface $store,
        int|string|Level $level = Level::Error,
        protected int $time = 60,
        bool $bubble = true,
        protected SignatureGenerator $signatureGenerator,
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
