<?php

namespace Naoray\LaravelGithubMonolog\Issues;

use Monolog\LogRecord;
use Throwable;

trait InteractsWithLogRecord
{
    protected function hasException(LogRecord $record): bool
    {
        return isset($record->context['exception'])
            && $record->context['exception'] instanceof Throwable;
    }

    protected function getException(LogRecord $record): ?Throwable
    {
        return $this->hasException($record) ? $record->context['exception'] : null;
    }
}
