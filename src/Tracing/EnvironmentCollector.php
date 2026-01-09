<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\DataCollectorInterface;

class EnvironmentCollector implements DataCollectorInterface
{
    public function isEnabled(): bool
    {
        $config = config('logging.channels.github.tracing', []);

        return isset($config['environment']) && $config['environment'];
    }

    /**
     * Collect environment data.
     */
    public function collect(): void
    {
        Context::add('environment', [
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'app_version' => config('app.version'),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'php_os' => PHP_OS,
            'hostname' => gethostname() ?: null,
            'git_commit' => config('app.git_commit'),
        ]);
    }
}
