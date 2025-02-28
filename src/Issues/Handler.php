<?php

namespace Naoray\LaravelGithubMonolog\Issues;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Issues\Formatters\Formatted;

class Handler extends AbstractProcessingHandler
{
    private const DEFAULT_LABEL = 'github-issue-logger';

    private PendingRequest $client;

    /**
     * @param  string  $repo  The GitHub repository in "owner/repo" format
     * @param  string  $token  Your GitHub Personal Access Token
     * @param  array  $labels  Labels to be applied to GitHub issues (default: ['github-issue-logger'])
     * @param  int|string|Level  $level  Log level (default: ERROR)
     * @param  bool  $bubble  Whether the messages that are handled can bubble up the stack
     */
    public function __construct(
        private string $repo,
        private string $token,
        protected array $labels = [],
        int|string|Level $level = Level::Error,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);

        $this->repo = $repo;
        $this->token = $token;
        $this->labels = array_unique(array_merge([self::DEFAULT_LABEL], $labels));
        $this->client = Http::withToken($this->token)->baseUrl('https://api.github.com');
    }

    /**
     * Override write to log issues to GitHub
     */
    protected function write(LogRecord $record): void
    {
        if (! $record->formatted instanceof Formatted) {
            throw new \RuntimeException('Record must be formatted with '.Formatted::class);
        }

        $formatted = $record->formatted;

        try {
            $existingIssue = $this->findExistingIssue($record);

            if ($existingIssue) {
                $this->commentOnIssue($existingIssue['number'], $formatted);

                return;
            }

            $this->createIssue($formatted);
        } catch (RequestException $e) {
            if ($e->response->serverError()) {
                throw $e;
            }

            $this->createFallbackIssue($formatted, $e->response->body());
        }
    }

    /**
     * Find an existing issue with the given signature
     */
    private function findExistingIssue(LogRecord $record): ?array
    {
        if (! isset($record->extra['github_issue_signature'])) {
            throw new \RuntimeException('Record is missing github_issue_signature in extra data. Make sure the DeduplicationHandler is configured correctly.');
        }

        return $this->client
            ->get('/search/issues', [
                'q' => "repo:{$this->repo} is:issue is:open label:".self::DEFAULT_LABEL." \"Signature: {$record->extra['github_issue_signature']}\"",
            ])
            ->throw()
            ->json('items.0', null);
    }

    /**
     * Add a comment to an existing issue
     */
    private function commentOnIssue(int $issueNumber, Formatted $formatted): void
    {
        $this->client
            ->post("/repos/{$this->repo}/issues/{$issueNumber}/comments", [
                'body' => $formatted->comment,
            ])
            ->throw();
    }

    /**
     * Create a new GitHub issue
     */
    private function createIssue(Formatted $formatted): void
    {
        $this->client
            ->post("/repos/{$this->repo}/issues", [
                'title' => $formatted->title,
                'body' => $formatted->body,
                'labels' => $this->labels,
            ])
            ->throw();
    }

    /**
     * Create a fallback issue when the main issue creation fails
     */
    private function createFallbackIssue(Formatted $formatted, string $errorMessage): void
    {
        $this->client
            ->post("/repos/{$this->repo}/issues", [
                'title' => '[GitHub Monolog Error] '.$formatted->title,
                'body' => "**Original Error Message:**\n{$formatted->body}\n\n**Integration Error:**\n{$errorMessage}",
                'labels' => array_merge($this->labels, ['monolog-integration-error']),
            ])
            ->throw();
    }
}
