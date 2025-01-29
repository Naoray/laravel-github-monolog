<?php

namespace Naoray\LaravelGithubMonolog\Issues;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Issues\Formatters\ExceptionFormatter;
use Throwable;

class TemplateRenderer
{
    private const TITLE_MAX_LENGTH = 100;

    private const MAX_PREVIOUS_EXCEPTIONS = 3;

    private string $issueStub;

    private string $commentStub;

    private string $previousExceptionStub;

    public function __construct(
        private readonly ExceptionFormatter $exceptionFormatter,
        private readonly StubLoader $stubLoader,
    ) {
        $this->issueStub = $this->stubLoader->load('issue');
        $this->commentStub = $this->stubLoader->load('comment');
        $this->previousExceptionStub = $this->stubLoader->load('previous_exception');
    }

    public function render(string $template, LogRecord $record, ?string $signature = null, ?Throwable $exception = null): string
    {
        $replacements = $this->buildReplacements($record, $signature, $exception);

        return Str::of($template)
            ->replace(array_keys($replacements), array_values($replacements))
            ->toString();
    }

    public function renderTitle(LogRecord $record, ?Throwable $exception = null): string
    {
        if (! $exception) {
            return Str::of('[{level}] {message}')
                ->replace('{level}', $record->level->getName())
                ->replace('{message}', Str::limit($record->message, self::TITLE_MAX_LENGTH))
                ->toString();
        }

        return $this->exceptionFormatter->formatTitle($exception, $record->level->getName());
    }

    public function getIssueStub(): string
    {
        return $this->issueStub;
    }

    public function getCommentStub(): string
    {
        return $this->commentStub;
    }

    private function buildReplacements(LogRecord $record, ?string $signature, ?Throwable $exception): array
    {
        $exceptionDetails = $exception ? $this->exceptionFormatter->format($record) : [];

        return array_filter([
            '{level}' => $record->level->getName(),
            '{message}' => $record->message,
            '{simplified_stack_trace}' => $exceptionDetails['simplified_stack_trace'] ?? '',
            '{full_stack_trace}' => $exceptionDetails['full_stack_trace'] ?? '',
            '{previous_exceptions}' => $exception ? $this->formatPrevious($exception) : '',
            '{context}' => $this->formatContext($record->context),
            '{extra}' => $this->formatExtra($record->extra),
            '{signature}' => $signature,
        ]);
    }

    private function formatPrevious(Throwable $exception): string
    {
        $previous = $exception->getPrevious();
        if (! $previous) {
            return '';
        }

        $exceptions = collect()
            ->range(1, self::MAX_PREVIOUS_EXCEPTIONS)
            ->map(function ($count) use (&$previous) {
                if (! $previous) {
                    return null;
                }

                $current = $previous;
                $previous = $previous->getPrevious();

                $details = $this->exceptionFormatter->format(new LogRecord(
                    datetime: new \DateTimeImmutable,
                    channel: 'github',
                    level: \Monolog\Level::Error,
                    message: '',
                    context: ['exception' => $current],
                    extra: []
                ));

                return Str::of($this->previousExceptionStub)
                    ->replace(
                        ['{count}', '{type}', '{simplified_stack_trace}', '{full_stack_trace}'],
                        [$count, get_class($current), $details['simplified_stack_trace'], str_replace(base_path(), '', $details['full_stack_trace'])]
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

    private function formatContext(array $context): string
    {
        if (empty($context)) {
            return '';
        }

        return sprintf(
            "**Context:**\n```json\n%s\n```\n",
            json_encode(Arr::except($context, ['exception']), JSON_PRETTY_PRINT)
        );
    }

    private function formatExtra(array $extra): string
    {
        if (empty($extra)) {
            return '';
        }

        return sprintf(
            "**Extra Data:**\n```json\n%s\n```",
            json_encode($extra, JSON_PRETTY_PRINT)
        );
    }
}
