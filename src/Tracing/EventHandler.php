<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Routing\Events\RouteMatched;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;

class EventHandler
{
    /**
     * Get all event-driven collectors mapped to their events.
     *
     * Returns an array where keys are event classes and values are either
     * a single collector class or an array of collector classes.
     *
     * @return array<string, class-string|array<class-string>>
     */
    protected static function getCollectors(): array
    {
        return [
            RequestHandled::class => [
                RequestDataCollector::class,
                LivewireDataCollector::class,
                InertiaDataCollector::class,
            ],
            RouteMatched::class => RouteDataCollector::class,
            Authenticated::class => UserDataCollector::class,
            QueryExecuted::class => QueryCollector::class,
            JobExceptionOccurred::class => JobContextCollector::class,
            CommandStarting::class => CommandContextCollector::class,
            RequestSending::class => OutgoingRequestSendingCollector::class,
            ResponseReceived::class => OutgoingRequestResponseCollector::class,
        ];
    }

    public function subscribe(Dispatcher $events): void
    {
        // Check package config first, then fall back to channel config
        $packageConfig = config('github-monolog.tracing', []);
        $channelConfig = config('logging.channels.github.tracing', []);

        // Package config takes precedence
        $enabled = $packageConfig['enabled'] ?? $channelConfig['enabled'] ?? true;

        if (! $enabled) {
            return;
        }

        foreach (self::getCollectors() as $eventClass => $collectors) {
            // Normalize to array for consistent handling
            $collectors = is_array($collectors) ? $collectors : [$collectors];

            foreach ($collectors as $collectorClass) {
                /** @var EventDrivenCollectorInterface $collector */
                $collector = new $collectorClass;

                if ($collector->isEnabled()) {
                    $events->listen($eventClass, function ($event) use ($collectorClass) {
                        /** @var EventDrivenCollectorInterface $collectorInstance */
                        $collectorInstance = new $collectorClass;

                        rescue(fn () => $collectorInstance($event));
                    });
                }
            }
        }

        // Register logout listener to remember user before logout
        $userCollector = new UserDataCollector;
        if ($userCollector->isEnabled()) {
            $events->listen(Logout::class, function (Logout $event) {
                rescue(fn () => (new UserDataCollector)->handleLogout($event));
            });
        }
    }
}
