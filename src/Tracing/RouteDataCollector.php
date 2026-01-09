<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;

class RouteDataCollector implements EventDrivenCollectorInterface
{
    public function isEnabled(): bool
    {
        $config = config('logging.channels.github.tracing', []);

        return isset($config['route']) && $config['route'];
    }

    public function __invoke(RouteMatched $event): void
    {
        $route = $event->route;
        $action = $route->getAction();

        Context::add('route', [
            'name' => $route->getName(),
            'uri' => $route->uri(),
            'parameters' => $route->parameters(),
            'controller' => $action['controller'] ?? null,
            'middleware' => $route->gatherMiddleware(),
            'methods' => $route->methods(),
        ]);
    }
}
