<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\RedactsData;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\ResolvesTracingConfig;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;

/**
 * Inertia.js data collector.
 *
 * This collector detects Inertia requests by examining request headers,
 * without requiring the Inertia package as a dependency. It captures
 * component and version information from the request structure.
 */
class InertiaDataCollector implements EventDrivenCollectorInterface
{
    use RedactsData;
    use ResolvesTracingConfig;

    public function isEnabled(): bool
    {
        return $this->isTracingFeatureEnabled('inertia');
    }

    public function __invoke(RequestHandled $event): void
    {
        if (! $this->isInertiaRequest($event->request)) {
            return;
        }

        $this->captureFromRequest($event->request, $event->response);
    }

    /**
     * Detect if the current request is an Inertia request.
     */
    protected function isInertiaRequest(Request $request): bool
    {
        return $request->hasHeader('X-Inertia');
    }

    /**
     * Capture Inertia request data.
     *
     * @param  \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\Response  $response
     */
    protected function captureFromRequest(Request $request, $response): void
    {
        $data = [
            'version' => $request->header('X-Inertia-Version'),
            'partial_reload' => $request->hasHeader('X-Inertia-Partial-Data'),
        ];

        // Capture partial reload details
        if ($data['partial_reload']) {
            $data['partial_component'] = $request->header('X-Inertia-Partial-Component');
            $data['partial_keys'] = $this->parsePartialKeys(
                $request->header('X-Inertia-Partial-Data')
            );
            $data['partial_except'] = $this->parsePartialKeys(
                $request->header('X-Inertia-Partial-Except')
            );
        }

        // Try to extract component name from response
        $component = $this->extractComponentFromResponse($response);
        if ($component !== null) {
            $data['component'] = $component;
        }

        // Add request URL for context
        $data['url'] = $request->fullUrl();

        // Filter nulls but keep false values (like partial_reload = false)
        Context::add('inertia', array_filter($data, fn ($value) => $value !== null));
    }

    /**
     * Parse comma-separated partial keys into an array.
     *
     * @return array<string>|null
     */
    protected function parsePartialKeys(?string $keys): ?array
    {
        if ($keys === null || $keys === '') {
            return null;
        }

        return array_map('trim', explode(',', $keys));
    }

    /**
     * Extract the Inertia component name from the response.
     *
     * @param  \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\Response  $response
     */
    protected function extractComponentFromResponse($response): ?string
    {
        // Check X-Inertia header in response
        if (! $response->headers->has('X-Inertia')) {
            return null;
        }

        // Try to parse component from JSON response
        $content = $response->getContent();
        if ($content === false || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return null;
        }

        return $decoded['component'] ?? null;
    }
}
