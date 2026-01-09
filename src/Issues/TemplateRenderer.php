<?php

namespace Naoray\LaravelGithubMonolog\Issues;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Issues\Formatters\ContextFormatter;
use Naoray\LaravelGithubMonolog\Issues\Formatters\ExceptionFormatter;
use Naoray\LaravelGithubMonolog\Issues\Formatters\ExtraFormatter;
use Naoray\LaravelGithubMonolog\Issues\Formatters\OutgoingRequestFormatter;
use Naoray\LaravelGithubMonolog\Issues\Formatters\PreviousExceptionFormatter;
use Naoray\LaravelGithubMonolog\Issues\Formatters\QueryFormatter;
use Naoray\LaravelGithubMonolog\Issues\Formatters\StructuredDataFormatter;
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
        private readonly StructuredDataFormatter $structuredDataFormatter,
        private readonly QueryFormatter $queryFormatter,
        private readonly OutgoingRequestFormatter $outgoingRequestFormatter,
        private readonly ContextFormatter $contextFormatter,
        private readonly ExtraFormatter $extraFormatter,
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
        $exceptionDetails = $this->exceptionFormatter->format($record);
        $exceptionData = $record->context['exception'] ?? null;

        $message = $exceptionDetails['message'] ?? $record->message;
        $class = $this->resolveExceptionClass($exception, $exceptionData);

        return [
            '{level}' => $record->level->getName(),
            '{message}' => $message,
            '{class}' => $class,
            '{signature}' => $signature ?? '',
            '{simplified_stack_trace}' => $exceptionDetails['simplified_stack_trace'] ?? '',
            '{full_stack_trace}' => $exceptionDetails['full_stack_trace'] ?? '',
            '{previous_exceptions}' => $this->hasException($record) ? $this->previousExceptionFormatter->format($record) : '',
            '{environment}' => $this->structuredDataFormatter->format($record->context['environment'] ?? null),
            '{request}' => $this->structuredDataFormatter->format($record->context['request'] ?? null),
            '{route}' => $this->structuredDataFormatter->format($record->context['route'] ?? null),
            '{user}' => $this->structuredDataFormatter->format($record->context['user'] ?? null),
            '{queries}' => $this->queryFormatter->format($record->context['queries'] ?? null),
            '{job}' => $this->structuredDataFormatter->format($record->context['job'] ?? null),
            '{command}' => $this->structuredDataFormatter->format($record->context['command'] ?? null),
            '{outgoing_requests}' => $this->outgoingRequestFormatter->format($record->context['outgoing_requests'] ?? null),
            '{session}' => $this->structuredDataFormatter->format($record->context['session'] ?? null),
            '{context}' => $this->contextFormatter->format($record->context),
            '{extra}' => $this->extraFormatter->format(Arr::except($record->extra, ['github_issue_signature'])),
        ];
    }

    private function resolveExceptionClass(?Throwable $exception, mixed $exceptionData): string
    {
        if ($exception instanceof Throwable) {
            return get_class($exception);
        }

        if (! is_string($exceptionData)) {
            return '';
        }

        if (preg_match('/^([A-Z][a-zA-Z0-9_\\\\]+Exception|Exception|Error|RuntimeException|InvalidArgumentException|LogicException)/', $exceptionData, $matches)) {
            return $matches[1];
        }

        return 'Exception';
    }

}
