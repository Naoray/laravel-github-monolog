<?php

namespace Naoray\LaravelGithubMonolog\Handlers;

use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Contracts\SignatureGenerator;
use Naoray\LaravelGithubMonolog\DefaultSignatureGenerator;

class SignatureDeduplicationHandler extends DeduplicationHandler
{
    private SignatureGenerator $signatureGenerator;

    public function __construct(
        HandlerInterface $handler,
        ?string $deduplicationStore = null,
        int|string|Level $deduplicationLevel = Level::Error,
        int $time = 60,
        bool $bubble = true,
        ?SignatureGenerator $signatureGenerator = null,
    ) {
        parent::__construct($handler, $deduplicationStore, $deduplicationLevel, $time, $bubble);
        $this->signatureGenerator = $signatureGenerator ?? new DefaultSignatureGenerator;
    }

    /**
     * Override isDuplicate to use our signature-based deduplication
     */
    protected function isDuplicate(array $store, LogRecord $record): bool
    {
        $timestampValidity = $record->datetime->getTimestamp() - $this->time;
        $signature = $this->signatureGenerator->generate($record);

        foreach ($store as $entry) {
            [$timestamp, $storedSignature] = explode(':', $entry, 2);

            if ($storedSignature === $signature && $timestamp > $timestampValidity) {
                return true;
            }
        }

        return false;
    }

    /**
     * Override store entry format to use our signature
     */
    protected function buildDeduplicationStoreEntry(LogRecord $record): string
    {
        return $record->datetime->getTimestamp().':'.$this->signatureGenerator->generate($record);
    }
}
