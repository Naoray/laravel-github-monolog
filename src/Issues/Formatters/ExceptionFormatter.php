<?php

namespace Naoray\LaravelGithubMonolog\Issues\Formatters;

use Illuminate\Support\Str;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;
use ReflectionClass;
use Throwable;

class ExceptionFormatter implements FormatterInterface
{
    private const TITLE_MAX_LENGTH = 100;

    public function __construct(
        private readonly StackTraceFormatter $stackTraceFormatter,
    ) {}

    public function format(LogRecord $record): array
    {
        $exception = $record->context['exception'] ?? null;
        if (! $exception instanceof Throwable) {
            return [];
        }

        $header = $this->formatHeader($exception);
        $stackTrace = $exception->getTraceAsString();

        return [
            'message' => $exception->getMessage(),
            'simplified_stack_trace' => $header."\n[stacktrace]\n".$this->stackTraceFormatter->format($stackTrace),
            'full_stack_trace' => $header."\n[stacktrace]\n".$this->stackTraceFormatter->format($stackTrace, collapseVendorFrames: false),
        ];
    }

    public function formatBatch(array $records): array
    {
        return array_map([$this, 'format'], $records);
    }

    public function formatTitle(Throwable $exception, string $level): string
    {
        $exceptionClass = (new ReflectionClass($exception))->getShortName();
        $file = Str::replace(base_path(), '', $exception->getFile());

        return Str::of('[{level}] {class} in {file}:{line} - {message}')
            ->replace('{level}', $level)
            ->replace('{class}', $exceptionClass)
            ->replace('{file}', $file)
            ->replace('{line}', (string) $exception->getLine())
            ->replace('{message}', Str::limit($exception->getMessage(), self::TITLE_MAX_LENGTH))
            ->toString();
    }

    private function formatHeader(Throwable $exception): string
    {
        return sprintf(
            '[%s] %s: %s at %s:%d',
            now()->format('Y-m-d H:i:s'),
            (new ReflectionClass($exception))->getShortName(),
            $exception->getMessage(),
            str_replace(base_path(), '', $exception->getFile()),
            $exception->getLine()
        );
    }
}
