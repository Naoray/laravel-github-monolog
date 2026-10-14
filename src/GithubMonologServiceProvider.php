<?php

namespace Naoray\LaravelGithubMonolog;

use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Naoray\LaravelGithubMonolog\Deduplication\BufferFlusher;
use Naoray\LaravelGithubMonolog\Tracing\EventHandler;

class GithubMonologServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerContextDehydration();
    }

    public function boot(): void
    {
        $config = config('logging.channels.github.tracing');

        if (isset($config['enabled']) && $config['enabled']) {
            Event::subscribe(EventHandler::class);
        }

        $this->registerBufferFlushing();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/github-monolog'),
            ], 'github-monolog-views');
        }
    }

    /**
     * Flush buffered GitHub handlers in long-lived processes, which otherwise
     * hold records until overflow or shutdown.
     *
     * Looping fires before each worker iteration, so it runs after the previous
     * job's exception was reported (JobAttempted fires before that) and keeps
     * firing while the worker is idle. WorkerStopping fires right before a
     * timed-out worker is killed with SIGKILL, which skips shutdown handlers.
     */
    protected function registerBufferFlushing(): void
    {
        // Looping is dispatched with until(): returning false would pause the
        // worker, so the listener must not return anything.
        $flush = function (): void {
            (new BufferFlusher($this->app->make('log')))->flush();
        };

        Event::listen(Looping::class, $flush);
        Event::listen(WorkerStopping::class, $flush);
        Event::listen('Laravel\Octane\Events\RequestTerminated', $flush);
    }

    /**
     * Register context dehydration callback to prevent large tracing data
     * from being serialized into job payloads.
     */
    protected function registerContextDehydration(): void
    {
        Context::dehydrating(function ($context) {
            $keysToForget = [
                'queries',
                'outgoing_requests',
                'breadcrumbs',
                'session',
                'request',
                'livewire',
                'livewire_originating_page',
                'inertia',
            ];

            // Tracing collectors store most of their payloads as hidden
            // context, so we have to clear both the visible and hidden stores.
            // Otherwise the request payload (which may contain an unserializable
            // UploadedFile) is serialized into job payloads and throws.
            foreach ($keysToForget as $key) {
                if ($context->has($key)) {
                    $context->forget($key);
                }

                if ($context->hasHidden($key)) {
                    $context->forgetHidden($key);
                }
            }

            // Clean up prefixed keys from both the visible and hidden stores.
            foreach (array_keys($context->all()) as $key) {
                if (str_starts_with($key, 'outgoing_request.')) {
                    $context->forget($key);
                }
            }

            foreach (array_keys($context->allHidden()) as $key) {
                if (str_starts_with($key, 'outgoing_request.')) {
                    $context->forgetHidden($key);
                }
            }
        });
    }
}
