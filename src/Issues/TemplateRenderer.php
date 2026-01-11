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

        // If exceptionDetails is empty but record message contains stack trace, try to format it
        if (empty($exceptionDetails) &&
            (str_contains($record->message, 'Stack trace:') || preg_match('/#\d+ \//', $record->message))) {
            $tempRecord = $record->with(context: array_merge($record->context, ['exception' => $record->message]));
            $exceptionDetails = $this->exceptionFormatter->format($tempRecord);
        }

        $message = $exceptionDetails['message'] ?? $this->extractMessageFromRecord($record->message);
        $class = $this->resolveExceptionClass($exception, $exceptionData);

        return [
            '{level}' => $record->level->getName(),
            '{message}' => $message,
            '{class}' => $class,
            '{signature}' => $signature ?? '',
            '{timestamp}' => $this->formatTimestamp($record),
            '{route_summary}' => $this->formatRouteSummary($record),
            '{user_summary}' => $this->formatUserSummary($record),
            '{environment_name}' => $this->extractEnvironmentName($record),
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

    private function formatTimestamp(LogRecord $record): string
    {
        return $record->datetime->format('Y-m-d H:i:s');
    }

    private function formatRouteSummary(LogRecord $record): string
    {
        $request = $record->context['request'] ?? null;
        $route = $record->context['route'] ?? null;

        // Try to get method and path from request context first
        if (is_array($request)) {
            $method = $request['method'] ?? '';
            $url = $request['url'] ?? '';

            if ($method !== '' && $url !== '') {
                $parsedUrl = parse_url($url);
                $path = $parsedUrl['path'] ?? '/';

                return strtoupper($method).' '.$path;
            }
        }

        // Fallback to route context if request context is missing or incomplete
        if (is_array($route)) {
            $methods = $route['methods'] ?? [];
            $uri = $route['uri'] ?? '';

            if (! empty($methods) && $uri !== '') {
                $method = is_array($methods) ? ($methods[0] ?? '') : $methods;
                if ($method !== '') {
                    return strtoupper($method).' /'.$uri;
                }
            }
        }

        return '';
    }

    private function formatUserSummary(LogRecord $record): string
    {
        $user = $record->context['user'] ?? null;

        if (! is_array($user)) {
            return 'Unauthenticated';
        }

        $id = $user['id'] ?? null;

        if ($id === null) {
            return 'Unauthenticated';
        }

        return (string) $id;
    }

    private function extractEnvironmentName(LogRecord $record): string
    {
        $environment = $record->context['environment'] ?? null;

        if (! is_array($environment)) {
            return '';
        }

        return $environment['APP_ENV'] ?? $environment['app_env'] ?? '';
    }

    /**
     * Extract just the message part from a record message that may contain stack trace.
     */
    private function extractMessageFromRecord(string $message): string
    {
        // If message contains stack trace, extract just the message part
        if (str_contains($message, 'Stack trace:') || preg_match('/#\d+ \//', $message)) {
            // Try to extract the message before the stack trace
            if (preg_match('/^(.*?)(?:Stack trace:|#\d+ \/)/', $message, $matches)) {
                $extracted = trim($matches[1]);

                // Remove exception class prefix (e.g., "RuntimeException: ")
                if (preg_match('/^[A-Z][a-zA-Z0-9_\\\\]+Exception: (.+)$/s', $extracted, $classMatches)) {
                    $extracted = trim($classMatches[1]);
                }

                // Remove file/line info if present (e.g., "message in /path/to/file.php:123")
                if (preg_match('/^(.*) in \/[^\s]+(?:\.php)?(?: on line \d+)?$/s', $extracted, $fileMatches)) {
                    return trim($fileMatches[1]);
                }

                return $extracted;
            }
        }

        return $message;
    }
}
