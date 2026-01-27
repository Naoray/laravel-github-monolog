<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\ResolvesTracingConfig;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;

class RouteDataCollector implements EventDrivenCollectorInterface
{
    use ResolvesTracingConfig;

    /**
     * Livewire internal route patterns that should be replaced with originating page.
     *
     * @var array<string>
     */
    protected static array $livewireRoutePatterns = [
        'livewire/message/*',
        'livewire/upload-file',
        'livewire/preview-file/*',
        'livewire/update',
    ];

    public function isEnabled(): bool
    {
        return $this->isTracingFeatureEnabled('route');
    }

    public function __invoke(RouteMatched $event): void
    {
        $route = $event->route;
        $action = $route->getAction();
        $uri = $route->uri();

        Context::add('route', [
            'name' => $route->getName(),
            'uri' => $uri,
            'parameters' => $route->parameters(),
            'controller' => $action['controller'] ?? null,
            'middleware' => $route->gatherMiddleware(),
            'methods' => $route->methods(),
        ]);

        // For route_summary, show originating page for Livewire routes
        $summary = $this->buildRouteSummary($route, $uri);
        Context::add('route_summary', $summary);
    }

    /**
     * Build a human-readable route summary.
     *
     * For Livewire internal routes, this returns the originating page instead.
     */
    protected function buildRouteSummary(\Illuminate\Routing\Route $route, string $uri): string
    {
        // Check if this is a Livewire internal route
        if ($this->isLivewireRoute($uri)) {
            // Try to get the originating page from Livewire context
            $originatingPage = Context::get('livewire_originating_page');
            if ($originatingPage) {
                return $originatingPage;
            }

            // Fall back to referer header
            $referer = request()->header('referer');
            if ($referer) {
                $parsed = parse_url($referer);

                return ($parsed['path'] ?? '/').
                    (isset($parsed['query']) ? '?'.$parsed['query'] : '');
            }
        }

        // For non-Livewire routes, use route name or URI
        $name = $route->getName();

        return $name ?? '/'.$uri;
    }

    /**
     * Check if the given URI is a Livewire internal route.
     */
    protected function isLivewireRoute(string $uri): bool
    {
        foreach (self::$livewireRoutePatterns as $pattern) {
            if (fnmatch($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }
}
