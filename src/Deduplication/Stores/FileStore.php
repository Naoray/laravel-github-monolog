<?php

namespace Naoray\LaravelGithubMonolog\Deduplication\Stores;

use Illuminate\Support\Facades\File;
use Monolog\LogRecord;
use RuntimeException;

class FileStore extends AbstractStore
{
    private string $path;

    private $handle = null;

    public function __construct(
        string $path,
        string $prefix = 'github-monolog:',
        int $time = 60
    ) {
        parent::__construct($prefix, $time);

        $this->path = $path;

        File::ensureDirectoryExists(dirname($this->path));
    }

    public function get(): array
    {
        $this->acquireLock();

        try {
            $content = $this->readContent();
            $entries = array_filter(explode(PHP_EOL, $content));

            // Filter out expired entries and rebuild file
            $valid = array_filter($entries, function ($entry) {
                if (! str_contains($entry, ':')) {
                    return false;
                }
                [$timestamp] = explode(':', $entry, 2);

                return is_numeric($timestamp) && ! $this->isExpired((int) $timestamp);
            });

            if (count($valid) !== count($entries)) {
                $this->writeContent(implode(PHP_EOL, $valid));
            }

            return $valid;
        } catch (RuntimeException $e) {
            // If we can't read the file, assume no entries
            return [];
        } finally {
            $this->releaseLock();
        }
    }

    public function add(LogRecord $record, string $signature): void
    {
        $this->acquireLock();

        try {
            $entry = $this->formatEntry($signature, time());
            $content = $this->readContent();

            $this->writeContent(
                ($content ? $content . PHP_EOL : '') . $entry
            );
        } finally {
            $this->releaseLock();
        }
    }

    private function acquireLock(): void
    {
        if ($this->handle !== null) {
            return; // Already have a lock
        }

        if (! $this->handle = fopen($this->path, 'c+')) {
            throw new RuntimeException("Cannot open file: {$this->path}");
        }

        $attempts = 3;
        while ($attempts--) {
            if (flock($this->handle, LOCK_EX | LOCK_NB)) {
                return;
            }
            if ($attempts) {
                usleep(100000); // Sleep for 100ms between attempts
            }
        }

        fclose($this->handle);
        $this->handle = null;
        throw new RuntimeException("Cannot acquire lock on file: {$this->path}");
    }

    private function releaseLock(): void
    {
        if ($this->handle) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }

    private function readContent(): string
    {
        if (! $this->handle) {
            throw new RuntimeException('File handle not initialized');
        }

        fseek($this->handle, 0);

        return stream_get_contents($this->handle) ?: '';
    }

    private function writeContent(string $content): void
    {
        if (! $this->handle) {
            throw new RuntimeException('File handle not initialized');
        }

        ftruncate($this->handle, 0);
        fseek($this->handle, 0);
        fwrite($this->handle, $content);
        fflush($this->handle);
    }

    public function __destruct()
    {
        $this->releaseLock();
    }
}
