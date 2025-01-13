<?php

namespace Naoray\LaravelGithubMonolog\Formatters;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;
use ReflectionClass;
use Throwable;

class GithubIssueFormatter implements FormatterInterface
{
    private const TITLE_MAX_LENGTH = 100;

    private const MAX_PREVIOUS_EXCEPTIONS = 3;

    private const VENDOR_FRAME_PLACEHOLDER = '[Vendor frames]';

    /**
     * Formats a log record.
     *
     * @param  LogRecord  $record  A record to format
     * @return GithubIssueFormatted The formatted record
     */
    public function format(LogRecord $record): GithubIssueFormatted
    {
        $exception = $this->getException($record);
        $signature = $this->generateSignature($record, $exception);

        return new GithubIssueFormatted(
            signature: $signature,
            title: $this->formatTitle($record, $exception),
            body: $this->formatBody($record, $signature, $exception),
            comment: $this->formatComment($record, $exception),
        );
    }

    /**
     * Formats a set of log records.
     *
     * @param  array<LogRecord>  $records  A set of records to format
     * @return array<GithubIssueFormatted> The formatted set of records
     */
    public function formatBatch(array $records): array
    {
        return array_map([$this, 'format'], $records);
    }

    /**
     * Generate a unique signature for the log record
     */
    private function generateSignature(LogRecord $record, ?Throwable $exception): string
    {
        if (! $exception) {
            return md5($record->message . json_encode($record->context));
        }

        $trace = $exception->getTrace();
        $firstFrame = ! empty($trace) ? $trace[0] : null;

        return md5(implode(':', [
            $exception::class,
            $exception->getFile(),
            $exception->getLine(),
            $firstFrame ? ($firstFrame['file'] ?? '') . ':' . ($firstFrame['line'] ?? '') : '',
        ]));
    }

    /**
     * Check if the record contains an error exception
     */
    private function hasErrorException(LogRecord $record): bool
    {
        return $record->level->value >= \Monolog\Level::Error->value
            && isset($record->context['exception'])
            && $record->context['exception'] instanceof Throwable;
    }

    /**
     * Get the exception from the record if it exists
     */
    private function getException(LogRecord $record): ?Throwable
    {
        return $this->hasErrorException($record) ? $record->context['exception'] : null;
    }

    private function formatTitle(LogRecord $record, ?Throwable $exception = null): string
    {
        if (! $exception) {
            return Str::of('[{level}] {message}')
                ->replace('{level}', $record->level->getName())
                ->replace('{message}', Str::limit($record->message, self::TITLE_MAX_LENGTH))
                ->toString();
        }

        $exceptionClass = (new ReflectionClass($exception))->getShortName();
        $file = Str::replace(base_path(), '', $exception->getFile());

        return Str::of('[{level}] {class} in {file}:{line} - {message}')
            ->replace('{level}', $record->level->getName())
            ->replace('{class}', $exceptionClass)
            ->replace('{file}', $file)
            ->replace('{line}', (string) $exception->getLine())
            ->replace('{message}', Str::limit($exception->getMessage(), self::TITLE_MAX_LENGTH))
            ->toString();
    }

    private function formatContent(LogRecord $record, ?Throwable $exception): string
    {
        return Str::of('')
            ->when($record->message, fn($str, $message) => $str->append("**Message:**\n{$message}\n\n"))
            ->when(
                $exception,
                function (Stringable $str, Throwable $exception) {
                    return $str->append(
                        $this->renderExceptionDetails($this->formatExceptionDetails($exception)),
                        $this->renderPreviousExceptions($this->formatPreviousExceptions($exception))
                    );
                }
            )
            ->when(! empty($record->context), fn($str, $context) => $str->append("**Context:**\n```json\n" . json_encode(Arr::except($record->context, ['exception']), JSON_PRETTY_PRINT) . "\n```\n\n"))
            ->when(! empty($record->extra), fn($str, $extra) => $str->append("**Extra Data:**\n```json\n" . json_encode($record->extra, JSON_PRETTY_PRINT) . "\n```\n"))
            ->toString();
    }

    private function formatBody(LogRecord $record, string $signature, ?Throwable $exception): string
    {
        return Str::of("**Log Level:** {$record->level->getName()}\n\n")
            ->append($this->formatContent($record, $exception))
            ->append("\n\n<!-- Signature: {$signature} -->")
            ->toString();
    }

    /**
     * Shamelessly stolen from Solo by @aarondfrancis
     *
     * See: https://github.com/aarondfrancis/solo/blob/main/src/Commands/EnhancedTailCommand.php
     */
    private function cleanStackTrace(string $stackTrace): string
    {
        return collect(explode("\n", $stackTrace))
            ->filter(fn($line) => ! empty(trim($line)))
            ->map(function ($line) {
                if (trim($line) === '"}') {
                    return '';
                }

                if (str_contains($line, '{"exception":"[object] ')) {
                    return $this->formatInitialException($line);
                }

                // Not a stack frame line, return as is
                if (! Str::isMatch('/#[0-9]+ /', $line)) {
                    return $line;
                }

                // Make the line shorter by removing the base path
                $line = str_replace(base_path(), '', $line);

                if (str_contains((string) $line, '/vendor/') && ! Str::isMatch("/BoundMethod\.php\([0-9]+\): App/", $line)) {
                    return self::VENDOR_FRAME_PLACEHOLDER;
                }

                return $line;
            })
            ->pipe($this->modifyWrappedLines(...))
            ->join("\n");
    }

    public function formatInitialException($line): array
    {
        [$message, $exception] = explode('{"exception":"[object] ', $line);

        return [
            $message,
            $exception,
        ];
    }

    protected function modifyWrappedLines(Collection $lines): Collection
    {
        $hasVendorFrame = false;

        // After all the lines have been wrapped, we look through them
        // to collapse consecutive vendor frames into a single line.
        return $lines->filter(function ($line) use (&$hasVendorFrame) {
            $isVendorFrame = str_contains($line, '[Vendor frames]');

            if ($isVendorFrame) {
                // Skip the line if a vendor frame has already been added.
                if ($hasVendorFrame) {
                    return false;
                }
                // Otherwise, mark that a vendor frame has been added.
                $hasVendorFrame = true;
            } else {
                // Reset the flag if the current line is not a vendor frame.
                $hasVendorFrame = false;
            }

            return true;
        });
    }

    private function formatExceptionDetails(Throwable $exception): array
    {
        $header = sprintf(
            '[%s] %s: %s at %s:%d',
            $this->getCurrentDateTime(),
            (new ReflectionClass($exception))->getShortName(),
            $exception->getMessage(),
            str_replace(base_path(), '', $exception->getFile()),
            $exception->getLine()
        );

        return [
            'message' => $exception->getMessage(),
            'stack_trace' => $header . "\n[stacktrace]\n" . $this->cleanStackTrace($exception->getTraceAsString()),
            'full_stack_trace' => $header . "\n[stacktrace]\n" . $exception->getTraceAsString(),
        ];
    }

    private function getCurrentDateTime(): string
    {
        return now()->format('Y-m-d H:i:s');
    }

    private function formatPreviousExceptions(Throwable $exception): array
    {
        $previous = $exception->getPrevious();
        if (! $previous) {
            return [];
        }

        return collect()
            ->range(1, self::MAX_PREVIOUS_EXCEPTIONS)
            ->map(function ($count) use (&$previous) {
                if (! $previous) {
                    return null;
                }

                $current = $previous;
                $previous = $previous->getPrevious();

                return [
                    'count' => $count,
                    'type' => get_class($current),
                    'details' => $this->formatExceptionDetails($current),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function renderExceptionDetails(array $details): string
    {
        $content = sprintf("**Simplified Stack Trace:**\n```php\n%s\n```\n\n", $details['stack_trace']);

        // Add the complete stack trace in details tag
        $content .= "**Complete Stack Trace:**\n";
        $content .= "<details>\n<summary>View full trace</summary>\n\n";
        $content .= sprintf("```php\n%s\n```\n", str_replace(base_path(), '', $details['full_stack_trace'] ?? $details['stack_trace']));
        $content .= "</details>\n\n";

        return $content;
    }

    private function renderPreviousExceptions(array $exceptions): string
    {
        if (empty($exceptions)) {
            return '';
        }

        $content = "<details>\n<summary>Previous Exceptions</summary>\n\n";

        foreach ($exceptions as $exception) {
            $content .= "### Previous Exception #{$exception['count']}\n";
            $content .= "**Type:** {$exception['type']}\n\n";
            $content .= $this->renderExceptionDetails($exception['details']);
        }

        if (count($exceptions) === self::MAX_PREVIOUS_EXCEPTIONS) {
            $content .= "\n> Note: Additional previous exceptions were truncated\n";
        }

        $content .= "</details>\n\n";

        return $content;
    }

    /**
     * Formats a log record for a comment on an existing issue.
     *
     * @param  LogRecord  $record  A record to format
     * @return string The formatted comment
     */
    public function formatComment(LogRecord $record, ?Throwable $exception): string
    {
        $body = "# New Occurrence\n\n";
        $body .= "**Log Level:** {$record->level->getName()}\n\n";
        $body .= $this->formatContent($record, $exception);

        return $body;
    }
}
