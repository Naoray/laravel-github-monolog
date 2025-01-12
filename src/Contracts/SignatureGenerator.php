<?php

namespace Naoray\LaravelGithubMonolog\Contracts;

use Monolog\LogRecord;

interface SignatureGenerator
{
    /**
     * Generate a unique signature for the log record
     */
    public function generate(LogRecord $record): string;
}
