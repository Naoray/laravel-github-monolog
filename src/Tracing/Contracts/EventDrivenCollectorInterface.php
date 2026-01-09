<?php

namespace Naoray\LaravelGithubMonolog\Tracing\Contracts;

/**
 * Marker interface for event-driven collectors.
 *
 * Event-driven collectors implement __invoke() to handle Laravel events
 * and are registered via EventHandler. They collect data when events fire.
 *
 * Classes implementing this interface are callable and can be registered
 * as event listeners in Laravel's event system.
 *
 * Note: Implementations should type-hint their specific event type
 * in the __invoke() method signature.
 */
interface EventDrivenCollectorInterface
{
    /**
     * Check if this collector is enabled.
     */
    public function isEnabled(): bool;
}
