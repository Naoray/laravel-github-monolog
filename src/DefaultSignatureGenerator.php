<?php

namespace Naoray\LaravelGithubMonolog;

use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Contracts\SignatureGenerator;
use Throwable;

class DefaultSignatureGenerator implements SignatureGenerator
{
    /**
     * Generate a unique signature for the log record
     */
    public function generate(LogRecord $record): string
    {
        $exception = $record->context['exception'] ?? null;

        if (! $exception instanceof Throwable) {
            return $this->generateFromMessage($record);
        }

        return $this->generateFromException($exception);
    }

    /**
     * Generate a signature from a message and context
     */
    private function generateFromMessage(LogRecord $record): string
    {
        return md5($record->message.json_encode($record->context));
    }

    /**
     * Generate a signature from an exception
     */
    private function generateFromException(Throwable $exception): string
    {
        $trace = $exception->getTrace();
        $firstFrame = ! empty($trace) ? $trace[0] : null;

        return md5(implode(':', [
            $exception::class,
            $exception->getFile(),
            $exception->getLine(),
            $firstFrame ? ($firstFrame['file'] ?? '').':'.($firstFrame['line'] ?? '') : '',
        ]));
    }
}
