<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class RequestDataCollector
{
    public function __invoke(RouteMatched $event): void
    {
        $route = $event->route;
        $request = $event->request;

        Context::add('request', [
            'url' => $request->url(),
            'method' => $request->method(),
            'route' => $route->getName(),
            'headers' => $this->filterSensitiveHeaders($request->headers->all()),
            'body' => $request->all(),
        ]);
    }

    private function filterSensitiveHeaders(array $headers): array
    {
        $sensitiveHeaders = $this->getSensitiveHeaders();

        return collect($headers)
            ->map(function ($value, $key) use ($sensitiveHeaders) {
                if (Str::is($sensitiveHeaders, $key, true)) {
                    return ['[FILTERED]'];
                }

                return $value;
            })
            ->toArray();
    }

    private function getSensitiveHeaders(): array
    {
        $sensitiveHeaders = [
            config('session.cookie'),
            'remember_*',
            'XSRF-TOKEN',
            'cookie',
        ];

        return collect($sensitiveHeaders)
            ->merge(collect($sensitiveHeaders)->map(fn ($header) => str($header)->replace('_', '-'))->toArray())
            ->unique()
            ->toArray();
    }
}
