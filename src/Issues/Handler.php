<?php

namespace Naoray\LaravelGithubMonolog\Issues;

use Illuminate\Support\Facades\Http;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Deduplication\SignatureGeneratorInterface;

class Handler extends AbstractProcessingHandler
{
    private const DEFAULT_LABEL = 'github-issue-logger';

    /**
     * @param  string  $repo  The GitHub repository in "owner/repo" format
     * @param  string  $token  Your GitHub Personal Access Token
     * @param  SignatureGeneratorInterface  $signatureGenerator  The signature generator to use
     * @param  array  $labels  Labels to be applied to GitHub issues (default: ['github-issue-logger'])
     * @param  int|string|Level  $level  Log level (default: ERROR)
     * @param  bool  $bubble  Whether the messages that are handled can bubble up the stack
     */
    public function __construct(
        private string $repo,
        private string $token,
        protected SignatureGeneratorInterface $signatureGenerator,
        protected array $labels = [],
        int|string|Level $level = Level::Error,
        bool $bubble = true,
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
        if (! $record->formatted instanceof Formatted) {
            throw new \RuntimeException('Record must be formatted with ' . Formatted::class);
        }

        $formatted = $record->formatted;
        $existingIssue = $this->findExistingIssue($record);

        if ($existingIssue) {
            $this->commentOnIssue($existingIssue['number'], $formatted);

            return;
        }

        $this->createIssue($formatted);
    }

    /**
     * Find an existing issue with the given signature
     */
    private function findExistingIssue(LogRecord $record): ?array
    {
        $response = Http::withToken($this->token)
            ->get('https://api.github.com/search/issues', [
                'q' => "repo:{$this->repo} is:issue is:open label:" . self::DEFAULT_LABEL . " \"Signature: {$this->signatureGenerator->generate($record)}\"",
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to search GitHub issues: ' . $response->body());
        }

        return $response->json('items.0', null);
    }

    /**
     * Add a comment to an existing issue
     */
    private function commentOnIssue(int $issueNumber, Formatted $formatted): void
    {
        $response = Http::withToken($this->token)
            ->post("https://api.github.com/repos/{$this->repo}/issues/{$issueNumber}/comments", [
                'body' => $formatted->comment, // TODO: fix
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to comment on GitHub issue: ' . $response->body());
        }
    }

    /**
     * Create a new GitHub issue
     */
    private function createIssue(Formatted $formatted): void
    {
        $response = Http::withToken($this->token)
            ->post("https://api.github.com/repos/{$this->repo}/issues", [
                'title' => $formatted->title,
                'body' => $formatted->body,
                'labels' => $this->labels,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to create GitHub issue: ' . $response->body());
        }
    }
}
