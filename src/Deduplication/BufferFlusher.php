<?php

namespace Naoray\LaravelGithubMonolog\Deduplication;

use Illuminate\Log\LogManager;
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
        } catch (Throwable) {
            // Never let error reporting break the worker loop. The failed
            // records are already marked as seen in the deduplication cache,
            // so retrying them later would drop them anyway.
            $handler->clear();
        }
    }
}
