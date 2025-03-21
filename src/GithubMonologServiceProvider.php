<?php

namespace Naoray\LaravelGithubMonolog;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Naoray\LaravelGithubMonolog\Tracing\EventHandler;

class GithubMonologServiceProvider extends ServiceProvider
{
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
}
