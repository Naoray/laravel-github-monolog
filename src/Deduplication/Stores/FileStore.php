<?php

namespace Naoray\LaravelGithubMonolog\Deduplication\Stores;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Monolog\LogRecord;

class FileStore extends AbstractStore
{
    public function __construct(
        protected string $path,
        string $prefix = 'github-monolog:',
        int $time = 60
    ) {
        parent::__construct($prefix, $time);

        File::ensureDirectoryExists(dirname($this->path));
    }

    public function get(): array
    {
        if ($this->fileIsMissing()) {
            return [];
        }

        return Str::of(File::get($this->path))
            ->explode(PHP_EOL)
            ->filter(fn($entry) => $entry && str_contains($entry, ':') && is_numeric(explode(':', $entry, 2)[0]))
            ->toArray();
    }

    public function add(LogRecord $record, string $signature): void
    {
        $entry = $this->buildEntry($signature, time());
        $content = File::exists($this->path) ? File::get($this->path) : '';

        File::put(
            $this->path,
            ($content ? $content . PHP_EOL : '') . $entry
        );
    }

    public function cleanup(): void
    {
        $timestampValidity = time() - $this->time;

        $valid = collect($this->get())
            ->filter(function ($entry) use ($timestampValidity) {
                [$timestamp] = explode(':', $entry, 2);
                return is_numeric($timestamp) && (int) $timestamp > $timestampValidity;
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
