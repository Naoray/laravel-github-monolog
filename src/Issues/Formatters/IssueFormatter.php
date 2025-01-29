<?php

namespace Naoray\LaravelGithubMonolog\Issues\Formatters;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Issues\TemplateRenderer;
use Throwable;

class IssueFormatter implements FormatterInterface
{
    public function __construct(
        private readonly TemplateRenderer $templateRenderer,
    ) {}

    public function format(LogRecord $record): Formatted
    {
        if (! isset($record->extra['github_issue_signature'])) {
            throw new \RuntimeException('Record is missing github_issue_signature in extra data. Make sure the DeduplicationHandler is configured correctly.');
        }

        $exception = $this->getException($record);

        return new Formatted(
            title: $this->templateRenderer->renderTitle($record, $exception),
            body: $this->templateRenderer->render($this->templateRenderer->getIssueStub(), $record, $record->extra['github_issue_signature'], $exception),
            comment: $this->templateRenderer->render($this->templateRenderer->getCommentStub(), $record, null, $exception),
        );
    }

    public function formatBatch(array $records): array
    {
        return array_map([$this, 'format'], $records);
    }

    private function hasErrorException(LogRecord $record): bool
    {
        return $record->level->value >= \Monolog\Level::Error->value
            && isset($record->context['exception'])
            && $record->context['exception'] instanceof Throwable;
    }

    private function getException(LogRecord $record): ?Throwable
    {
        return $this->hasErrorException($record) ? $record->context['exception'] : null;
    }
}
