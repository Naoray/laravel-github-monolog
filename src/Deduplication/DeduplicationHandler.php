<?php

namespace Naoray\LaravelGithubMonolog\Handlers;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Contracts\SignatureGenerator;
use Naoray\LaravelGithubMonolog\DeduplicationStores\DeduplicationStoreInterface;
use Naoray\LaravelGithubMonolog\DeduplicationStores\RedisDeduplicationStore;
use Naoray\LaravelGithubMonolog\DefaultSignatureGenerator;

class SignatureDeduplicationHandler extends AbstractProcessingHandler
{
    private SignatureGenerator $signatureGenerator;

    private HandlerInterface $handler;

    private DeduplicationStoreInterface $store;

    private int $time;

    public function __construct(
        HandlerInterface $handler,
        ?DeduplicationStoreInterface $store = null,
        int|string|Level $level = Level::Error,
        int $time = 60,
        bool $bubble = true,
        ?SignatureGenerator $signatureGenerator = null,
    ) {
        parent::__construct($level, $bubble);

        $this->handler = $handler;
        $this->time = $time;
        $this->store = $store ?? new RedisDeduplicationStore(time: $time);
        $this->signatureGenerator = $signatureGenerator ?? new DefaultSignatureGenerator;
    }

    protected function write(LogRecord $record): void
    {
        $signature = $this->signatureGenerator->generate($record);

        if ($this->isDuplicate($record, $signature)) {
            return;
        }

        $this->store->add($record, $signature);
        $this->handler->handle($record);
    }

    protected function isDuplicate(LogRecord $record, string $signature): bool
    {
        $store = $this->store->get();
        $timestampValidity = time() - $this->time;

        foreach ($store as $entry) {
            [$timestamp, $storedSignature] = explode(':', $entry, 2);
            if ($storedSignature === $signature && (int) $timestamp > $timestampValidity) {
                return true;
            }
        }

        return false;
    }
}
