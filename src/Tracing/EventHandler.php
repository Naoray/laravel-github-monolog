<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Auth\Events\Authenticated;
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
     * @return array<string, class-string>
     */
    protected static function getCollectors(): array
    {
        return [
            RequestHandled::class => RequestDataCollector::class,
            RouteMatched::class => RouteDataCollector::class,
            Authenticated::class => UserDataCollector::class,
            QueryExecuted::class => QueryCollector::class,
            JobExceptionOccurred::class => JobContextCollector::class,
            CommandStarting::class => CommandContextCollector::class,
            RequestSending::class => OutgoingRequestSendingCollector::class,
            ResponseReceived::class => OutgoingRequestResponseCollector::class,
        ];
    }

    public function subscribe(Dispatcher $events)
    {
        $config = config('logging.channels.github.tracing', []);

        if (isset($config['enabled']) && ! $config['enabled']) {
            return;
        }

        foreach (self::getCollectors() as $eventClass => $collectorClass) {
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
}
