<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class CallerFrameProcessor implements ProcessorInterface
{
    /**
     * Capture caller frame for message-only records
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        // Skip if exception is present (exception traces are handled separately)
        if (isset($record->context['exception'])) {
            return $record;
        }

        $caller = $this->findCallerFrame();

        if ($caller === null) {
            return $record;
        }

        return $record->with(
            extra: array_merge($record->extra, ['caller' => $caller])
        );
    }

    /**
     * Find the first caller frame outside vendor and this package
     *
     * @return array{file: string, func: string}|null
     */
    private function findCallerFrame(): ?array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? null;

            if (! is_string($file) || $file === '') {
                continue;
            }

            // Skip vendor frames
            if (str_contains($file, '/vendor/')) {
                continue;
            }

            // Skip this package's frames
            if (str_contains($file, 'laravel-github-monolog')) {
                continue;
            }

            // Skip artisan
            if (str_contains($file, '/artisan')) {
                continue;
            }

            // Found a non-vendor, non-package frame
            $func = ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'];

            return [
                'file' => $this->normalizePath($file),
                'func' => $func,
            ];
        }

        return null;
    }

    /**
     * Normalize a file path by stripping base path and normalizing separators
     */
    private function normalizePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        // Strip base_path() if available (same approach as DefaultSignatureGenerator)
        if (function_exists('base_path')) {
            $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            if (str_starts_with($path, $base)) {
                $path = substr($path, strlen($base));
            }
        }

        return str_replace('\\', '/', $path);
    }
}
