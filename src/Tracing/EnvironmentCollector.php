<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\ResolvesTracingConfig;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\DataCollectorInterface;

class EnvironmentCollector implements DataCollectorInterface
{
    use ResolvesTracingConfig;

    public function __construct(
        protected ?GitInfoDetector $gitInfoDetector = null,
    ) {
        $this->gitInfoDetector ??= new GitInfoDetector;
    }

    public function isEnabled(): bool
    {
        return $this->isTracingFeatureEnabled('environment');
    }

    /**
     * Collect environment data.
     */
    public function collect(): void
    {
        $environment = [
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'app_version' => config('app.version'),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'php_os' => PHP_OS,
            'hostname' => gethostname() ?: null,
        ];

        $environment = array_merge($environment, $this->collectGitInfo());

        Context::add('environment', $environment);
    }

    /**
     * Collect git information if enabled.
     *
     * @return array<string, string|bool|null>
     */
    protected function collectGitInfo(): array
    {
        if (! $this->getTracingConfig('git', true)) {
            return ['git_commit' => config('app.git_commit')];
        }

        $gitInfo = $this->gitInfoDetector->detect();

        // config('app.git_commit') overrides auto-detected git_hash
        $configCommit = config('app.git_commit');
        if ($configCommit !== null) {
            $gitInfo['git_hash'] = $configCommit;
        }

        // Keep backward compatibility: include git_commit key
        $gitInfo['git_commit'] = $gitInfo['git_hash'] ?? $configCommit;

        return $gitInfo;
    }
}
