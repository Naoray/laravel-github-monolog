<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\RedactsData;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\ResolvesTracingConfig;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;

/**
 * Livewire data collector (v3+ only).
 *
 * This collector detects Livewire requests by examining request headers and payload,
 * without requiring the Livewire package as a dependency. It captures component data
 * from the request structure.
 */
class LivewireDataCollector implements EventDrivenCollectorInterface
{
    use RedactsData;
    use ResolvesTracingConfig;

    public function isEnabled(): bool
    {
        return $this->isTracingFeatureEnabled('livewire');
    }

    public function __invoke(RequestHandled $event): void
    {
        if (! $this->isLivewireRequest($event->request)) {
            return;
        }

        $this->captureFromRequest($event->request);
    }

    /**
     * Detect if the current request is a Livewire request.
     */
    protected function isLivewireRequest(Request $request): bool
    {
        // Livewire 3+ sends X-Livewire header
        if ($request->hasHeader('X-Livewire')) {
            return true;
        }

        // Also check for Livewire update endpoint
        $path = $request->path();

        return str_contains($path, 'livewire/update')
            || str_contains($path, 'livewire/message');
    }

    /**
     * Capture Livewire component data from the request.
     */
    protected function captureFromRequest(Request $request): void
    {
        $payload = $request->json()->all();
        $components = $this->extractComponents($payload);

        if (empty($components)) {
            return;
        }

        $data = [
            'components' => $components,
            'url' => $request->fullUrl(),
        ];

        // Extract originating page from the first component
        $originatingPage = $this->resolveOriginatingPage($payload, $request);
        if ($originatingPage !== null) {
            $data['originating_page'] = $originatingPage;
            Context::add('livewire_originating_page', $originatingPage);
        }

        Context::add('livewire', $this->redactPayload($data));
    }

    /**
     * Extract component information from the Livewire payload.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function extractComponents(array $payload): array
    {
        $components = [];

        // Livewire 3 structure: components array with snapshot
        foreach ($payload['components'] ?? [] as $component) {
            $snapshot = $component['snapshot'] ?? [];
            $memo = is_string($snapshot)
                ? $this->decodeSnapshot($snapshot)['memo'] ?? []
                : $snapshot['memo'] ?? [];

            $componentData = [
                'name' => $memo['name'] ?? null,
                'id' => $memo['id'] ?? null,
                'path' => $memo['path'] ?? null,
            ];

            // Extract method calls with parameters
            $calls = $component['calls'] ?? [];
            if (! empty($calls)) {
                $componentData['methods'] = collect($calls)
                    ->filter(fn ($call) => isset($call['method']))
                    ->map(fn ($call) => [
                        'method' => $call['method'],
                        'params' => $call['params'] ?? [],
                    ])
                    ->values()
                    ->toArray();
            }

            // Extract updated properties with values (wire:model updates)
            $updates = $component['updates'] ?? [];
            if (! empty($updates)) {
                $componentData['updates'] = $updates;
            }

            $components[] = array_filter($componentData);
        }

        return $components;
    }

    /**
     * Decode a JSON-encoded snapshot string.
     *
     * @return array<string, mixed>
     */
    protected function decodeSnapshot(string $snapshot): array
    {
        $decoded = json_decode($snapshot, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Resolve the originating page the user was on.
     */
    protected function resolveOriginatingPage(array $payload, Request $request): ?string
    {
        // Try to get path from first component's snapshot
        $firstComponent = $payload['components'][0] ?? null;
        if ($firstComponent) {
            $snapshot = $firstComponent['snapshot'] ?? [];
            $memo = is_string($snapshot)
                ? $this->decodeSnapshot($snapshot)['memo'] ?? []
                : $snapshot['memo'] ?? [];

            if (isset($memo['path'])) {
                return $memo['path'];
            }
        }

        // Legacy: check fingerprint.path
        $fingerprint = $request->input('fingerprint.path');
        if ($fingerprint !== null) {
            return $fingerprint;
        }

        // Fall back to HTTP referer
        $referer = $request->header('referer');
        if ($referer !== null) {
            $parsed = parse_url($referer);

            return ($parsed['path'] ?? '/').
                (isset($parsed['query']) ? '?'.$parsed['query'] : '');
        }

        return null;
    }
}
