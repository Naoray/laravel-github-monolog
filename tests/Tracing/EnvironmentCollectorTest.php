<?php

use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\EnvironmentCollector;

beforeEach(function () {
    $this->collector = new EnvironmentCollector;
});

afterEach(function () {
    Context::flush();
});

it('collects environment data', function () {
    $this->collector->collect();

    $environment = Context::get('environment');

    expect($environment)->toHaveKeys(['app_env', 'laravel_version', 'php_version', 'php_os']);
    expect($environment['app_env'])->toBe(config('app.env'));
    expect($environment['laravel_version'])->toBe(app()->version());
    expect($environment['php_version'])->toBe(PHP_VERSION);
    expect($environment['php_os'])->toBe(PHP_OS);
});
