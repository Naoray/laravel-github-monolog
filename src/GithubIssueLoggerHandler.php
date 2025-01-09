<?php

namespace Naoray\LaravelGithubMonolog;

use Illuminate\Support\Facades\Http;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class GithubIssueLoggerHandler extends AbstractProcessingHandler
{
    private string $repo;

    private string $token;

    private array $labels;

    private const DEFAULT_LABEL = 'github-issue-logger';

    /**
     * @param  string  $repo  The GitHub repository in "owner/repo" format
     * @param  string  $token  Your GitHub Personal Access Token
     * @param  array  $labels  Labels to be applied to GitHub issues (default: ['github-issue-logger'])
     * @param  int|string|Level  $level  Log level (default: DEBUG)
     * @param  bool  $bubble  Whether the messages that are handled can bubble up the stack
     */
    public function __construct(
        string $repo,
        string $token,
        array $labels = [],
        $level = Level::Error,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);

        $this->repo = $repo;
        $this->token = $token;
        $this->labels = array_unique(array_merge([self::DEFAULT_LABEL], $labels));
    }

    /**
     * Override write to log issues to GitHub
     */
    protected function write(LogRecord $record): void
    {
        if (! $record->formatted instanceof GithubIssueFormatted) {
            throw new \RuntimeException('Record must be formatted with GithubIssueFormatter');
        }

        $formatted = $record->formatted;
        $existingIssue = $this->findExistingIssue($formatted->signature);

        if ($existingIssue) {
            $this->commentOnIssue($existingIssue['number'], $formatted);

            return;
        }

        $this->createIssue($formatted);
    }

    /**
     * Find an existing issue with the given signature
     */
    private function findExistingIssue(string $signature): ?array
    {
        $response = Http::withToken($this->token)
            ->get('https://api.github.com/search/issues', [
                'q' => "repo:{$this->repo} is:issue is:open label:".static::DEFAULT_LABEL." \"Signature: {$signature}\"",
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to search GitHub issues: '.$response->body());
        }

        return $response->json('items.0', null);
    }

    /**
     * Add a comment to an existing issue
     */
    private function commentOnIssue(int $issueNumber, GithubIssueFormatted $formatted): void
    {
        $response = Http::withToken($this->token)
            ->post("https://api.github.com/repos/{$this->repo}/issues/{$issueNumber}/comments", [
                'body' => $formatted->comment,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to comment on GitHub issue: '.$response->body());
        }
    }

    /**
     * Create a new GitHub issue
     */
    private function createIssue(GithubIssueFormatted $formatted): void
    {
        $response = Http::withToken($this->token)
            ->post("https://api.github.com/repos/{$this->repo}/issues", [
                'title' => $formatted->title,
                'body' => $formatted->body,
                'labels' => $this->labels,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to create GitHub issue: '.$response->body());
        }
    }
}
