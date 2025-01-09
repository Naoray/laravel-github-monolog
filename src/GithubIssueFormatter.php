<?php

namespace Naoray\LaravelGithubMonolog;

use Illuminate\Support\Str;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;
use ReflectionClass;
use Throwable;

class GithubIssueFormatter implements FormatterInterface
{
    private const TITLE_MAX_LENGTH = 100;

    private const MAX_PREVIOUS_EXCEPTIONS = 3;

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
        if ($exception) {
            $trace = $exception->getTrace();
            $firstFrame = ! empty($trace) ? $trace[0] : null;

            return md5(implode(':', [
                get_class($exception),
                $exception->getFile(),
                $exception->getLine(),
                $firstFrame ? ($firstFrame['file'] ?? '').':'.($firstFrame['line'] ?? '') : '',
            ]));
        }

        return md5($record->message.json_encode($record->context));
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
        if ($exception) {
            $exceptionClass = (new ReflectionClass($exception))->getShortName();
            $file = basename($exception->getFile());

            return Str::of('[{level}] {class} in {file}:{line} - {message}')
                ->replace('{level}', $record->level->getName())
                ->replace('{class}', $exceptionClass)
                ->replace('{file}', $file)
                ->replace('{line}', $exception->getLine())
                ->replace('{message}', Str::limit($exception->getMessage(), self::TITLE_MAX_LENGTH))
                ->toString();
        }

        return Str::of('[{level}] {message}')
            ->replace('{level}', $record->level->getName())
            ->replace('{message}', Str::limit($record->message, self::TITLE_MAX_LENGTH))
            ->toString();
    }

    private function formatBody(LogRecord $record, string $signature, ?Throwable $exception): string
    {
        $body = "**Log Level:** {$record->level->getName()}\n\n";

        if (! empty($record->message)) {
            $body .= "**Message:**\n{$record->message}\n\n";
        }

        if ($exception) {
            $details = $this->formatExceptionDetails($exception);
            $body .= $this->renderExceptionDetails($details);
            $previousExceptions = $this->formatPreviousExceptions($exception);
            $body .= $this->renderPreviousExceptions($previousExceptions);
        } elseif (! empty($record->context)) {
            $body .= "**Context:**\n```json\n".json_encode($record->context, JSON_PRETTY_PRINT)."\n```\n\n";
        }

        if (! empty($record->extra)) {
            $body .= "**Extra Data:**\n```json\n".json_encode($record->extra, JSON_PRETTY_PRINT)."\n```\n";
        }

        $body .= "\n\n<!-- Signature: {$signature} -->";

        return $body;
    }

    private function formatExceptionDetails(Throwable $exception, bool $isPrevious = false): array
    {
        $details = [];

        if (! $isPrevious) {
            $details['message'] = $exception->getMessage();
            $details['stack_trace'] = $exception->getTraceAsString();
        } else {
            $details['message'] = $exception->getMessage();
            $details['stack_trace'] = $exception->getTraceAsString();
        }

        return $details;
    }

    private function formatPreviousExceptions(Throwable $exception): array
    {
        $previous = $exception->getPrevious();
        if (! $previous) {
            return [];
        }

        $exceptions = [];
        $count = 1;
        while ($previous && $count <= self::MAX_PREVIOUS_EXCEPTIONS) {
            $exceptions[] = [
                'count' => $count,
                'type' => get_class($previous),
                'details' => $this->formatExceptionDetails($previous, true),
            ];
            $previous = $previous->getPrevious();
            $count++;
        }

        return $exceptions;
    }

    private function renderExceptionDetails(array $details, bool $isComment = false): string
    {
        $content = "**Message:**\n```\n{$details['message']}\n```\n\n";

        if ($isComment) {
            $content .= "<details>\n<summary>Stack Trace</summary>\n\n```\n{$details['stack_trace']}\n```\n</details>\n\n";
        } else {
            $content .= "**Stack Trace:**\n```\n{$details['stack_trace']}\n```\n\n";
        }

        return $content;
    }

    private function renderPreviousExceptions(array $exceptions, bool $isComment = false): string
    {
        if (empty($exceptions)) {
            return '';
        }

        if ($isComment) {
            $content = "<details>\n<summary>Previous Exceptions</summary>\n\n";
        } else {
            $content = "## Previous Exceptions\n\n";
        }

        foreach ($exceptions as $exception) {
            $content .= "### Previous Exception #{$exception['count']}\n";
            $content .= "**Type:** {$exception['type']}\n\n";
            $content .= $this->renderExceptionDetails($exception['details'], $isComment);
        }

        if (count($exceptions) === self::MAX_PREVIOUS_EXCEPTIONS) {
            $content .= "\n> Note: Additional previous exceptions were truncated\n";
        }

        if ($isComment) {
            $content .= "</details>\n\n";
        }

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
        $body = "**New Occurrence:**\n\n";

        if (! empty($record->message)) {
            $body .= "**Message:**\n{$record->message}\n\n";
        }

        if ($exception) {
            $details = $this->formatExceptionDetails($exception);
            $body .= $this->renderExceptionDetails($details, true);
            $previousExceptions = $this->formatPreviousExceptions($exception);
            $body .= $this->renderPreviousExceptions($previousExceptions, true);
        } elseif (! empty($record->context)) {
            $body .= "**Context:**\n```json\n".json_encode($record->context, JSON_PRETTY_PRINT)."\n```\n\n";
        }

        if (! empty($record->extra)) {
            $body .= "**Extra Data:**\n```json\n".json_encode($record->extra, JSON_PRETTY_PRINT)."\n```\n";
        }

        return $body;
    }
}
