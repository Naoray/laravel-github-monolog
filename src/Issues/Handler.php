<?php

namespace Naoray\LaravelGithubMonolog\Issues;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Auth\PersonalAccessTokenProvider;
use Naoray\LaravelGithubMonolog\Auth\TokenProviderInterface;
use Naoray\LaravelGithubMonolog\Issues\Formatters\Formatted;

class Handler extends AbstractProcessingHandler
{
    private const DEFAULT_LABEL = 'github-issue-logger';

    private TokenProviderInterface $token;

    /**
     * @param  string  $repo  The GitHub repository in "owner/repo" format
     * @param  string|TokenProviderInterface  $token  A GitHub API token or token provider
     * @param  array  $labels  Labels to be applied to GitHub issues (default: ['github-issue-logger'])
     * @param  int|string|Level  $level  Log level (default: ERROR)
     * @param  bool  $bubble  Whether the messages that are handled can bubble up the stack
     */
    public function __construct(
        private string $repo,
        string|TokenProviderInterface $token,
        protected array $labels = [],
        int|string|Level $level = Level::Error,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);

        $this->repo = $repo;
        $this->token = is_string($token) ? new PersonalAccessTokenProvider($token) : $token;
        $this->labels = array_unique(array_merge([self::DEFAULT_LABEL], $labels));
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
        $client = Http::withToken($this->token->getToken())->baseUrl('https://api.github.com');

        try {
            $existingIssue = $this->findExistingIssue($client, $record);

            if ($existingIssue) {
                $this->commentOnIssue($client, $existingIssue['number'], $formatted);

                return;
            }

            $this->createIssue($client, $formatted);
        } catch (RequestException $e) {
            if ($e->response->serverError()) {
                throw $e;
            }

            $this->createFallbackIssue($client, $formatted, $e->response->body());
        }
    }

    /**
     * Find an existing issue with the given signature
     */
    private function findExistingIssue(PendingRequest $client, LogRecord $record): ?array
    {
        if (! isset($record->extra['github_issue_signature'])) {
            throw new \RuntimeException('Record is missing github_issue_signature in extra data. Make sure the DeduplicationHandler is configured correctly.');
        }

        return $client
            ->get('/search/issues', [
                'q' => "repo:{$this->repo} is:issue is:open label:".self::DEFAULT_LABEL." \"Signature: {$record->extra['github_issue_signature']}\"",
            ])
            ->throw()
            ->json('items.0', null);
    }

    /**
     * Add a comment to an existing issue
     */
    private function commentOnIssue(PendingRequest $client, int $issueNumber, Formatted $formatted): void
    {
        $client
            ->post("/repos/{$this->repo}/issues/{$issueNumber}/comments", [
                'body' => $formatted->comment,
            ])
            ->throw();
    }

    /**
     * Create a new GitHub issue
     */
    private function createIssue(PendingRequest $client, Formatted $formatted): void
    {
        $client
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
    private function createFallbackIssue(PendingRequest $client, Formatted $formatted, string $errorMessage): void
    {
        $client
            ->post("/repos/{$this->repo}/issues", [
                'title' => '[GitHub Monolog Error] '.$formatted->title,
                'body' => "**Original Error Message:**\n{$formatted->body}\n\n**Integration Error:**\n{$errorMessage}",
                'labels' => array_merge($this->labels, ['monolog-integration-error']),
            ])
            ->throw();
    }
}
