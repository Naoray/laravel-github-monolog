<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\RedactsData;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;

class QueryCollector implements EventDrivenCollectorInterface
{
    use RedactsData;

    private const DEFAULT_LIMIT = 10;

    public function isEnabled(): bool
    {
        $config = config('logging.channels.github.tracing.queries', []);

        return isset($config['enabled']) && $config['enabled'];
    }

    public function __invoke(QueryExecuted $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $config = config('logging.channels.github.tracing.queries', []);

        $limit = $config['limit'] ?? self::DEFAULT_LIMIT;

        $queries = Context::get('queries', []);
        $queries[] = [
            'sql' => $event->sql,
            'bindings' => $this->redactBindings($event->bindings),
            'time' => $event->time,
            'connection' => $event->connectionName,
        ];

        // Keep only the last N queries
        if (count($queries) > $limit) {
            $queries = array_slice($queries, -$limit);
        }

        Context::add('queries', $queries);
    }
}
