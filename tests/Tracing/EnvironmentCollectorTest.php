<?php

use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\EnvironmentCollector;
use Naoray\LaravelGithubMonolog\Tracing\GitInfoDetector;

beforeEach(function () {
    GitInfoDetector::resetCache();
});

afterEach(function () {
    Context::flush();
    GitInfoDetector::resetCache();
});

it('collects environment data', function () {
    $collector = new EnvironmentCollector;
    $collector->collect();

    $environment = Context::get('environment');

    expect($environment)->toHaveKeys(['app_env', 'laravel_version', 'php_version', 'php_os']);
    expect($environment['app_env'])->toBe(config('app.env'));
    expect($environment['laravel_version'])->toBe(app()->version());
    expect($environment['php_version'])->toBe(PHP_VERSION);
    expect($environment['php_os'])->toBe(PHP_OS);
});

it('includes git data when available', function () {
    $detector = Mockery::mock(GitInfoDetector::class);
    $detector->shouldReceive('detect')->once()->andReturn([
        'git_hash' => 'abc1234',
        'git_branch' => 'main',
        'git_tag' => 'v1.0.0',
        'git_dirty' => false,
    ]);

    $collector = new EnvironmentCollector($detector);
    $collector->collect();

    $environment = Context::get('environment');

    expect($environment)
        ->toHaveKey('git_hash', 'abc1234')
        ->toHaveKey('git_branch', 'main')
        ->toHaveKey('git_tag', 'v1.0.0')
        ->toHaveKey('git_dirty', false)
        ->toHaveKey('git_commit', 'abc1234');
});

it('falls back gracefully when git is not available', function () {
    $detector = Mockery::mock(GitInfoDetector::class);
    $detector->shouldReceive('detect')->once()->andReturn([]);

    $collector = new EnvironmentCollector($detector);
    $collector->collect();

    $environment = Context::get('environment');

    expect($environment)
        ->toHaveKey('git_commit')
        ->toHaveKey('app_env')
        ->toHaveKey('php_version');

    expect($environment)->not->toHaveKey('git_hash');
    expect($environment)->not->toHaveKey('git_branch');
});

it('uses config override for git_hash when app.git_commit is set', function () {
    config(['app.git_commit' => 'config-hash-override']);

    $detector = Mockery::mock(GitInfoDetector::class);
    $detector->shouldReceive('detect')->once()->andReturn([
        'git_hash' => 'auto-detected-hash',
        'git_branch' => 'main',
    ]);

    $collector = new EnvironmentCollector($detector);
    $collector->collect();

    $environment = Context::get('environment');

    expect($environment)
        ->toHaveKey('git_hash', 'config-hash-override')
        ->toHaveKey('git_commit', 'config-hash-override')
        ->toHaveKey('git_branch', 'main');
});

it('skips git detection when git tracing is disabled', function () {
    config(['github-monolog.tracing.git' => false]);

    $detector = Mockery::mock(GitInfoDetector::class);
    $detector->shouldNotReceive('detect');

    $collector = new EnvironmentCollector($detector);
    $collector->collect();

    $environment = Context::get('environment');

    expect($environment)->toHaveKey('git_commit');
    expect($environment)->not->toHaveKey('git_hash');
    expect($environment)->not->toHaveKey('git_branch');
    expect($environment)->not->toHaveKey('git_tag');
    expect($environment)->not->toHaveKey('git_dirty');
});

it('sets git_commit from git_hash when config override is not set', function () {
    config(['app.git_commit' => null]);

    $detector = Mockery::mock(GitInfoDetector::class);
    $detector->shouldReceive('detect')->once()->andReturn([
        'git_hash' => 'detected-abc',
    ]);

    $collector = new EnvironmentCollector($detector);
    $collector->collect();

    $environment = Context::get('environment');

    expect($environment)
        ->toHaveKey('git_hash', 'detected-abc')
        ->toHaveKey('git_commit', 'detected-abc');
});

it('enables git tracing by default', function () {
    $detector = Mockery::mock(GitInfoDetector::class);
    $detector->shouldReceive('detect')->once()->andReturn([
        'git_hash' => 'abc1234',
    ]);

    $collector = new EnvironmentCollector($detector);
    $collector->collect();

    $environment = Context::get('environment');

    expect($environment)->toHaveKey('git_hash', 'abc1234');
});
