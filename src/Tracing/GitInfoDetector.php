<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Support\Facades\Process;

class GitInfoDetector
{
    /**
     * Cached git information (null means not yet resolved).
     *
     * @var array<string, string|bool|null>|null
     */
    protected static ?array $cachedGitInfo = null;

    /**
     * Detect git information for the current working directory.
     *
     * Results are cached statically so git commands only run once per process.
     *
     * @return array<string, string|bool|null>
     */
    public function detect(): array
    {
        if (static::$cachedGitInfo !== null) {
            return static::$cachedGitInfo;
        }

        static::$cachedGitInfo = [];

        $hash = $this->runGitCommand('git log --pretty="%h" -n1 HEAD');
        if ($hash !== null) {
            static::$cachedGitInfo['git_hash'] = $hash;
        }

        $branch = $this->runGitCommand('git rev-parse --abbrev-ref HEAD');
        if ($branch !== null) {
            static::$cachedGitInfo['git_branch'] = $branch;
        }

        $tag = $this->runGitCommand('git describe --tags --abbrev=0 2>/dev/null');
        if ($tag !== null) {
            static::$cachedGitInfo['git_tag'] = $tag;
        }

        $porcelain = $this->runGitCommand('git status --porcelain');
        if ($porcelain !== null) {
            static::$cachedGitInfo['git_dirty'] = $porcelain !== '';
        }

        return static::$cachedGitInfo;
    }

    /**
     * Run a git command with a 1-second timeout.
     *
     * Returns the trimmed output on success, or null on failure.
     */
    protected function runGitCommand(string $command): ?string
    {
        try {
            $result = Process::timeout(1)->path(base_path())->run($command);

            return $result->successful() ? trim($result->output()) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Reset the cached git information.
     *
     * Useful for testing or when the working directory changes.
     */
    public static function resetCache(): void
    {
        static::$cachedGitInfo = null;
    }
}
