<?php

namespace Naoray\LaravelGithubMonolog;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
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

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/github-monolog'),
            ], 'github-monolog-views');
        }
    }

    /**
     * Register context dehydration callback to prevent large tracing data
     * from being serialized into job payloads.
     */
    protected function registerContextDehydration(): void
    {
        Context::dehydrating(function ($context) {
            foreach (['queries', 'outgoing_requests', 'session', 'request'] as $key) {
                if ($context->has($key)) {
                    $context->forget($key);
                }
            }

            foreach (array_keys($context->all()) as $key) {
                if (str_starts_with($key, 'outgoing_request.')) {
                    $context->forget($key);
                }
            }
        });
    }
}
