<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Support\Facades\Context;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class ContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        // Collect environment data if enabled
        $environmentCollector = new EnvironmentCollector;
        if ($environmentCollector->isEnabled()) {
            $environmentCollector->collect();
        }

        // Collect user data if enabled and not already collected
        $userCollector = new UserDataCollector;
        if ($userCollector->isEnabled() && ! Context::has('user')) {
            $userCollector->collect();
        }

        // Collect session data if enabled
        $sessionCollector = new SessionCollector;
        if ($sessionCollector->isEnabled()) {
            $sessionCollector->collect();
        }

        $contextData = Context::all();

        if (empty($contextData)) {
            return $record;
        }

        return $record->with(
            context: array_merge($record->context, $contextData)
        );
    }
}
