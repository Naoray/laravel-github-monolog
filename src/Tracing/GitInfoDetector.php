<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

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
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes, base_path());

        if (! is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);

        $output = '';
        $startTime = microtime(true);
        $timeoutSeconds = 1.0;

        while (! feof($pipes[1])) {
            $elapsed = microtime(true) - $startTime;
            if ($elapsed >= $timeoutSeconds) {
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_terminate($process);
                proc_close($process);

                return null;
            }

            $chunk = fread($pipes[1], 8192);
            if ($chunk !== false) {
                $output .= $chunk;
            }

            if ($chunk === '' || $chunk === false) {
                usleep(10000); // 10ms
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            return null;
        }

        return trim($output);
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
