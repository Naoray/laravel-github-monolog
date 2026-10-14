<?php

namespace Naoray\LaravelGithubMonolog\Deduplication;

use Illuminate\Log\LogManager;
use Illuminate\Support\Str;
use Throwable;

/**
 * Flushes every buffered GitHub handler on the channels resolved so far.
 *
 * Buffered records are normally only sent on overflow or at process
 * shutdown, which a long-lived queue worker or Octane server rarely reaches.
 */
class BufferFlusher
{
    public function __construct(
        private readonly LogManager $log,
    ) {}

    public function flush(): void
    {
        foreach ($this->log->getChannels() as $channel) {
            if (! method_exists($channel, 'getLogger')) {
                continue;
            }

            $logger = $channel->getLogger();

            if (! method_exists($logger, 'getHandlers')) {
                continue;
            }

            // Stack channels reuse the handler instances of their member
            // channels; flushing twice is harmless since the buffer is empty.
            foreach ($logger->getHandlers() as $handler) {
                if ($handler instanceof DeduplicationHandler) {
                    $this->flushHandler($handler);
                }
            }
        }
    }

    private function flushHandler(DeduplicationHandler $handler): void
    {
        try {
            $handler->flush();
        } catch (Throwable $e) {
            // Never let error reporting break the worker loop. The failed
            // records are already marked as seen in the deduplication cache,
            // so retrying them later would drop them anyway. Logging through
            // Laravel could route back into this handler, so use error_log.
            error_log(sprintf(
                'laravel-github-monolog: dropped buffered records after a failed GitHub flush: %s: %s',
                $e::class,
                // A failed request's message includes part of GitHub's response body.
                Str::limit($e->getMessage(), 500),
            ));

            $handler->clear();
        }
    }
}
