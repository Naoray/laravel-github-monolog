<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\RedactsData;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\ResolvesTracingConfig;

/**
 * Livewire data collector.
 *
 * This collector is purely event-driven and captures Livewire component data
 * when Livewire lifecycle events fire. It does not support on-demand collection
 * because Livewire component instances are only available during their lifecycle.
 */
class LivewireDataCollector
{
    use RedactsData;
    use ResolvesTracingConfig;

    public function isEnabled(): bool
    {
        return $this->isTracingFeatureEnabled('livewire');
    }

    /**
     * Handle Livewire 3 hydrate event.
     *
     * @param  object  $component  Livewire component instance
     */
    public function hydrate(object $component): void
    {
        $this->captureComponent($component);
    }

    /**
     * Handle Livewire 2 component.hydrate.subsequent event.
     *
     * @param  object  $component  Livewire component instance
     */
    public function componentHydrateSubsequent(object $component): void
    {
        $this->captureComponent($component);
    }

    /**
     * Capture Livewire component data.
     *
     * @param  object  $component  Livewire component instance
     */
    protected function captureComponent(object $component): void
    {
        $data = [
            'component' => $component::class,
            'component_name' => method_exists($component, 'getName') ? $component->getName() : $component::class,
        ];

        // Capture the URL accessed during the error (the Livewire endpoint)
        $request = request();
        $data['url'] = $request->fullUrl();

        // Try to get the originating page from fingerprint (Livewire 3) or referrer
        $originatingPage = $this->resolveOriginatingPage($component);
        if ($originatingPage !== null) {
            $data['originating_page'] = $originatingPage;

            // Store originating page for route summary
            Context::add('livewire_originating_page', $originatingPage);
        }

        Context::add('livewire', $this->redactPayload($data));
    }

    /**
     * Resolve the originating page the user was on.
     *
     * @param  object  $component  Livewire component instance
     */
    protected function resolveOriginatingPage(object $component): ?string
    {
        // Livewire 3: Try to get path from component's snapshot/fingerprint
        if (method_exists($component, 'getSnapshot')) {
            /** @var array<string, mixed>|null $snapshot */
            $snapshot = $component->getSnapshot();
            if (isset($snapshot['memo']['path'])) {
                return $snapshot['memo']['path'];
            }
        }

        // Livewire 2 & 3: Check for path in request data
        $request = request();

        // Livewire 3 sends fingerprint in request
        $fingerprint = $request->input('components.0.snapshot.memo.path')
            ?? $request->input('fingerprint.path')
            ?? null;

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
