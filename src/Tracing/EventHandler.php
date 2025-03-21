<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Events\RouteMatched;

class EventHandler
{
    public function subscribe(Dispatcher $events)
    {
        $config = config('logging.channels.github.tracing');

        if (isset($config['requests']) && $config['requests']) {
            $events->listen(RouteMatched::class, RequestDataCollector::class);
        }

        if (isset($config['user']) && $config['user']) {
            $events->listen(Authenticated::class, UserDataCollector::class);
        }
    }
}
