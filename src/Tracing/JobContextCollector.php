<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\RedactsData;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;

class JobContextCollector implements EventDrivenCollectorInterface
{
    use RedactsData;

    public function isEnabled(): bool
    {
        $config = config('logging.channels.github.tracing', []);

        return isset($config['jobs']) && $config['jobs'];
    }

    public function __invoke(JobProcessing $event): void
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
