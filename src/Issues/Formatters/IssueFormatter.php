<?php

namespace Naoray\LaravelGithubMonolog\Issues\Formatters;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Issues\TemplateRenderer;

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

        return new Formatted(
            title: $this->templateRenderer->renderTitle($record),
            body: $this->templateRenderer->render($this->templateRenderer->getIssueStub(), $record, $record->extra['github_issue_signature']),
            comment: $this->templateRenderer->render($this->templateRenderer->getCommentStub(), $record, null),
        );
    }

    public function formatBatch(array $records): array
    {
        return array_map([$this, 'format'], $records);
    }
}
