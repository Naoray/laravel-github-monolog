<?php

namespace Naoray\LaravelGithubMonolog;

use Illuminate\Support\ServiceProvider;
use Naoray\LaravelGithubMonolog\Issues\Formatters\ExceptionFormatter;
use Naoray\LaravelGithubMonolog\Issues\Formatters\IssueFormatter;
use Naoray\LaravelGithubMonolog\Issues\Formatters\StackTraceFormatter;
use Naoray\LaravelGithubMonolog\Issues\StubLoader;
use Naoray\LaravelGithubMonolog\Issues\TemplateRenderer;

class GithubMonologServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StackTraceFormatter::class);
        $this->app->bind(StubLoader::class);
        $this->app->bind(ExceptionFormatter::class, function ($app) {
            return new ExceptionFormatter(
                stackTraceFormatter: $app->make(StackTraceFormatter::class),
            );
        });

        $this->app->singleton(TemplateRenderer::class, function ($app) {
            return new TemplateRenderer(
                exceptionFormatter: $app->make(ExceptionFormatter::class),
                stubLoader: $app->make(StubLoader::class),
            );
        });

        $this->app->singleton(IssueFormatter::class, function ($app) {
            return new IssueFormatter(
                templateRenderer: $app->make(TemplateRenderer::class),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/github-monolog'),
            ], 'github-monolog-views');
        }
    }
}
