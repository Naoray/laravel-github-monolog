<?php

namespace Naoray\LaravelGithubMonolog\Deduplication;

use Monolog\LogRecord;

interface SignatureGeneratorInterface
{
    /**
     * Generate a unique signature for the log record
     */
    public function generate(LogRecord $record): string;
}
