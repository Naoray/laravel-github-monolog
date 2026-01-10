<?php

namespace Naoray\LaravelGithubMonolog\Deduplication;

use Monolog\LogRecord;
use Throwable;

class DefaultSignatureGenerator implements SignatureGeneratorInterface
{
    public function __construct(
        private readonly VendorFrameDetector $vendorFrameDetector = new VendorFrameDetector
    ) {}

    /**
     * Generate a unique signature for the log record
     */
    public function generate(LogRecord $record): string
    {
        $exception = $record->context['exception'] ?? null;

        if ($exception instanceof Throwable) {
            return $this->generateFromException($exception, $record);
        }

        return $this->generateFromMessage($record);
    }

    /**
     * Generate a signature from a message and context
     */
    private function generateFromMessage(LogRecord $record): string
    {
        // Avoid hashing full context (it often contains per-request noise)
        $stable = [
            'message' => $this->normalizeMessage($record->message),
            'channel' => $record->channel,
            'level' => $record->level->name,
            'context' => $this->getContextIdentifier($record),
        ];

        return hash('sha256', json_encode($stable, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Generate a signature from an exception
     */
    private function generateFromException(Throwable $exception, LogRecord $record): string
    {
        $frame = $this->firstInAppFrame($exception) ?? $this->firstFrame($exception);

        $parts = [
            'ex' => $exception::class,
            // prefer in-app origin; avoid line numbers
            'file' => $frame ? $this->normalizePath($frame['file'] ?? '') : $this->normalizePath($exception->getFile()),
            'func' => $frame ? (($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '')) : '',
            'message' => $this->normalizeMessage($exception->getMessage()),
            'context' => $this->getContextIdentifier($record),
        ];

        return hash('sha256', json_encode($parts, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get the first frame from exception trace
     *
     * @return array<string, mixed>|null
     */
    private function firstFrame(Throwable $e): ?array
    {
        $trace = $e->getTrace();

        return $trace[0] ?? null;
    }

    /**
     * Find the first in-app frame (non-vendor) from exception trace
     *
     * @return array<string, mixed>|null
     */
    private function firstInAppFrame(Throwable $e): ?array
    {
        foreach ($e->getTrace() as $frame) {
            if ($this->vendorFrameDetector->isVendorFrame($frame)) {
                continue;
            }

            return $frame;
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

        // Strip base_path() if available (same approach as StackTraceFormatter)
        if (function_exists('base_path')) {
            $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            if (str_starts_with($path, $base)) {
                $path = substr($path, strlen($base));
            }
        }

        return str_replace('\\', '/', $path);
    }

    /**
     * Get context identifier with fallback chain: route → job → command
     *
     * @return string|null
     */
    private function getContextIdentifier(LogRecord $record): ?string
    {
        // Try route first
        $routeData = data_get($record->context, 'request.route') ?? data_get($record->context, 'route');

        if (is_array($routeData)) {
            return $routeData['name'] ?? $routeData['uri'] ?? null;
        }

        if (is_string($routeData)) {
            return $routeData;
        }

        // Fall back to job class
        $jobClass = data_get($record->context, 'job.class');
        if ($jobClass) {
            return $jobClass;
        }

        // Fall back to command name
        $commandName = data_get($record->context, 'command.name');
        if ($commandName) {
            return $commandName;
        }

        return null;
    }

    /**
     * Normalize a message by replacing unstable values (UUIDs, large numbers)
     */
    private function normalizeMessage(string $message): string
    {
        // Replace UUIDs
        $result = preg_replace(
            '/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i',
            '{uuid}',
            $message
        );

        // Replace long numbers (IDs, timestamps)
        $result = preg_replace('/\b\d{6,}\b/', '{num}', $result ?? $message);

        return is_string($result) ? $result : $message;
    }
}
