<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\RedactsData;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\ResolvesTracingConfig;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;

class JobContextCollector implements EventDrivenCollectorInterface
{
    use RedactsData;
    use ResolvesTracingConfig;

    public function isEnabled(): bool
    {
        return $this->isTracingFeatureEnabled('jobs');
    }

    public function __invoke(JobExceptionOccurred $event): void
    {
        $job = $event->job;

        Context::add('job', [
            'name' => $job->getName(),
            'class' => $job->payload()['displayName'] ?? null,
            'queue' => $job->getQueue(),
            'connection' => $job->getConnectionName(),
            'attempts' => $job->attempts(),
            'payload' => $this->redactPayload($job->payload()),
        ]);
    }
}
