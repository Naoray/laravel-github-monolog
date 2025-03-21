<?php

namespace Naoray\LaravelGithubMonolog\Issues;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Issues\Formatters\ExceptionFormatter;
use Naoray\LaravelGithubMonolog\Issues\Formatters\PreviousExceptionFormatter;
use Throwable;

class TemplateRenderer
{
    use InteractsWithLogRecord;

    private const TITLE_MAX_LENGTH = 100;

    private string $issueStub;

    private string $commentStub;

    public function __construct(
        private readonly ExceptionFormatter $exceptionFormatter,
        private readonly PreviousExceptionFormatter $previousExceptionFormatter,
        private readonly TemplateSectionCleaner $sectionCleaner,
        private readonly StubLoader $stubLoader,
    ) {
        $this->issueStub = $this->stubLoader->load('issue');
        $this->commentStub = $this->stubLoader->load('comment');
    }

    public function render(string $template, LogRecord $record, ?string $signature = null): string
    {
        $replacements = $this->buildReplacements($record, $signature);

        return $this->sectionCleaner->clean($template, $replacements);
    }

    public function renderTitle(LogRecord $record): string
    {
        $exception = $this->getException($record);

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

    private function buildReplacements(LogRecord $record, ?string $signature): array
    {
        $exception = $this->getException($record);
        $exceptionDetails = $exception instanceof Throwable ? $this->exceptionFormatter->format($record) : [];

        return [
            // Core replacements (always present)
            '{level}' => $record->level->getName(),
            '{message}' => $record->message,
            '{class}' => $exception instanceof Throwable ? get_class($exception) : '',
            '{signature}' => $signature ?? '',

            // Section replacements (may be empty)
            '{simplified_stack_trace}' => $exceptionDetails['simplified_stack_trace'] ?? '',
            '{full_stack_trace}' => $exceptionDetails['full_stack_trace'] ?? '',
            '{previous_exceptions}' => $this->hasException($record) ? $this->previousExceptionFormatter->format($record) : '',
            '{context}' => $this->formatContext($record->context),
            '{extra}' => $this->formatExtra(Arr::except($record->extra, ['github_issue_signature'])),
        ];
    }

    private function formatContext(array $context): string
    {
        $context = Arr::except($context, ['exception']);

        if (empty($context)) {
            return '';
        }

        return json_encode($context, JSON_PRETTY_PRINT);
    }

    private function formatExtra(array $extra): string
    {
        if (empty($extra)) {
            return '';
        }

        return json_encode($extra, JSON_PRETTY_PRINT);
    }
}
