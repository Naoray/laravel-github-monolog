<?php

namespace Naoray\LaravelGithubMonolog\Issues\Formatters;

use Illuminate\Support\Str;
use Monolog\LogRecord;
use Throwable;
use Naoray\LaravelGithubMonolog\Issues\InteractsWithLogRecord;
use Naoray\LaravelGithubMonolog\Issues\StubLoader;

class PreviousExceptionFormatter
{
    use InteractsWithLogRecord;

    private const MAX_PREVIOUS_EXCEPTIONS = 3;

    private string $previousExceptionStub;

    public function __construct(
        private readonly ExceptionFormatter $exceptionFormatter,
        private readonly StubLoader $stubLoader,
    ) {
        $this->previousExceptionStub = $this->stubLoader->load('previous_exception');
    }

    public function format(LogRecord $record): string
    {
        $exception = $this->getException($record);

        if (! $exception instanceof Throwable) {
            return '';
        }

        if (! $previous = $exception->getPrevious()) {
            return '';
        }

        $exceptions = collect()
            ->range(1, self::MAX_PREVIOUS_EXCEPTIONS)
            ->map(function ($count) use (&$previous, $record) {
                if (! $previous) {
                    return null;
                }

                $current = $previous;
                $previous = $previous->getPrevious();

                $details = $this->exceptionFormatter->format(
                    $record->with(
                        context: ['exception' => $current],
                        extra: []
                    )
                );

                return Str::of($this->previousExceptionStub)
                    ->replace(
                        ['{count}', '{message}', '{simplified_stack_trace}', '{full_stack_trace}'],
                        [$count, $current->getMessage(), $details['simplified_stack_trace'], str_replace(base_path(), '', $details['full_stack_trace'])]
                    )
                    ->toString();
            })
            ->filter()
            ->join("\n\n");

        if (empty($exceptions)) {
            return '';
        }

        if ($previous) {
            $exceptions .= "\n\n> Note: Additional previous exceptions were truncated\n";
        }

        return $exceptions;
    }
}
