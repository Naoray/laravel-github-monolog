<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\RedactsData;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;

class CommandContextCollector implements EventDrivenCollectorInterface
{
    use RedactsData;

    public function isEnabled(): bool
    {
        $config = config('logging.channels.github.tracing', []);

        return isset($config['commands']) && $config['commands'];
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
