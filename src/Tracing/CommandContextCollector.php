<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\RedactsData;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\ResolvesTracingConfig;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;

class CommandContextCollector implements EventDrivenCollectorInterface
{
    use RedactsData;
    use ResolvesTracingConfig;

    public function isEnabled(): bool
    {
        return $this->isTracingFeatureEnabled('commands');
    }

    public function __invoke(CommandStarting $event): void
    {
        Context::add('command', [
            'name' => $event->command,
            'arguments' => $event->input->getArguments(),
            'options' => $this->redactPayload($event->input->getOptions()),
        ]);
    }
}
