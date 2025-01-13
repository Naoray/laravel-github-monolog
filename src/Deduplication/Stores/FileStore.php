<?php

namespace Naoray\LaravelGithubMonolog\Deduplication\Stores;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Monolog\LogRecord;

class FileStore extends AbstractStore
{
    public function __construct(
        protected string $path,
        int $time = 60
    ) {
        parent::__construct($time);

        File::ensureDirectoryExists(dirname($this->path));
    }

    public function get(): array
    {
        if ($this->fileIsMissing()) {
            return [];
        }

        return Str::of(File::get($this->path))
            ->explode(PHP_EOL)
            ->filter(fn ($entry) => $entry && str_contains($entry, ':') && is_numeric(explode(':', $entry, 2)[0]))
            ->toArray();
    }

    public function add(LogRecord $record, string $signature): void
    {
        $entry = $this->buildEntry($signature, $this->getTimestamp());
        $content = File::exists($this->path) ? File::get($this->path) : '';

        File::put(
            $this->path,
            ($content ? $content.PHP_EOL : '').$entry
        );
    }

    public function cleanup(): void
    {
        $valid = collect($this->get())
            ->filter(function ($entry) {
                [$timestamp] = explode(':', $entry, 2);

                return is_numeric($timestamp) && ! $this->isExpired((int) $timestamp);
            })
            ->join(PHP_EOL);

        // overwrite the file with the new content
        File::put($this->path, $valid);
    }

    protected function fileIsMissing(): bool
    {
        return File::missing($this->path);
    }
}
