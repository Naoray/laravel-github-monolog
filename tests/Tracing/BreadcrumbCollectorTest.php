<?php

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\BreadcrumbCollector;

beforeEach(function () {
    Context::flush();
    BreadcrumbCollector::reset();
    $this->collector = new BreadcrumbCollector;
    Config::set('logging.channels.github.tracing.breadcrumbs', true);
    Config::set('logging.channels.github.tracing.breadcrumb_limit', 40);
});

afterEach(function () {
    Context::flush();
    BreadcrumbCollector::reset();
});

it('collects log messages as breadcrumbs', function () {
    $event = new MessageLogged('info', 'User logged in', []);

    $this->collector->handleMessageLogged($event);

    $breadcrumbs = BreadcrumbCollector::getBreadcrumbs();

    expect($breadcrumbs)->toHaveCount(1);
    expect($breadcrumbs[0])->toHaveKeys(['timestamp', 'category', 'message', 'metadata']);
    expect($breadcrumbs[0]['category'])->toBe('log');
    expect($breadcrumbs[0]['message'])->toBe('[info] User logged in');
});

it('collects multiple log levels', function () {
    $this->collector->handleMessageLogged(new MessageLogged('debug', 'Debug message', []));
    $this->collector->handleMessageLogged(new MessageLogged('info', 'Info message', []));
    $this->collector->handleMessageLogged(new MessageLogged('notice', 'Notice message', []));
    $this->collector->handleMessageLogged(new MessageLogged('warning', 'Warning message', []));

    $breadcrumbs = BreadcrumbCollector::getBreadcrumbs();

    expect($breadcrumbs)->toHaveCount(4);
    expect($breadcrumbs[0]['message'])->toBe('[debug] Debug message');
    expect($breadcrumbs[1]['message'])->toBe('[info] Info message');
    expect($breadcrumbs[2]['message'])->toBe('[notice] Notice message');
    expect($breadcrumbs[3]['message'])->toBe('[warning] Warning message');
});

it('excludes error-level log messages', function () {
    $this->collector->handleMessageLogged(new MessageLogged('error', 'Something broke', []));

    expect(BreadcrumbCollector::getBreadcrumbs())->toBeEmpty();
});

it('excludes critical-level log messages', function () {
    $this->collector->handleMessageLogged(new MessageLogged('critical', 'Critical failure', []));

    expect(BreadcrumbCollector::getBreadcrumbs())->toBeEmpty();
});

it('excludes alert-level log messages', function () {
    $this->collector->handleMessageLogged(new MessageLogged('alert', 'Alert!', []));

    expect(BreadcrumbCollector::getBreadcrumbs())->toBeEmpty();
});

it('excludes emergency-level log messages', function () {
    $this->collector->handleMessageLogged(new MessageLogged('emergency', 'Emergency!', []));

    expect(BreadcrumbCollector::getBreadcrumbs())->toBeEmpty();
});

it('collects cache hit events', function () {
    $event = new CacheHit('array', 'user.123', 'cached-value');

    $this->collector->handleCacheHit($event);

    $breadcrumbs = BreadcrumbCollector::getBreadcrumbs();

    expect($breadcrumbs)->toHaveCount(1);
    expect($breadcrumbs[0]['category'])->toBe('cache');
    expect($breadcrumbs[0]['message'])->toBe('Cache hit: user.123');
    expect($breadcrumbs[0]['metadata'])->toBe(['store' => 'array']);
});

it('collects cache missed events', function () {
    $event = new CacheMissed('array', 'user.456');

    $this->collector->handleCacheMissed($event);

    $breadcrumbs = BreadcrumbCollector::getBreadcrumbs();

    expect($breadcrumbs)->toHaveCount(1);
    expect($breadcrumbs[0]['category'])->toBe('cache');
    expect($breadcrumbs[0]['message'])->toBe('Cache miss: user.456');
    expect($breadcrumbs[0]['metadata'])->toBe(['store' => 'array']);
});

it('caps breadcrumbs at configured limit', function () {
    Config::set('logging.channels.github.tracing.breadcrumb_limit', 5);

    for ($i = 0; $i < 10; $i++) {
        $this->collector->handleMessageLogged(new MessageLogged('info', "Message {$i}", []));
    }

    $breadcrumbs = BreadcrumbCollector::getBreadcrumbs();

    expect($breadcrumbs)->toHaveCount(5);
    // Should keep the last 5 entries (5-9)
    expect($breadcrumbs[0]['message'])->toBe('[info] Message 5');
    expect($breadcrumbs[4]['message'])->toBe('[info] Message 9');
});

it('uses default limit of 40 when not configured', function () {
    Config::set('logging.channels.github.tracing.breadcrumb_limit', null);

    for ($i = 0; $i < 50; $i++) {
        $this->collector->handleMessageLogged(new MessageLogged('info', "Message {$i}", []));
    }

    $breadcrumbs = BreadcrumbCollector::getBreadcrumbs();

    expect($breadcrumbs)->toHaveCount(40);
    expect($breadcrumbs[0]['message'])->toBe('[info] Message 10');
    expect($breadcrumbs[39]['message'])->toBe('[info] Message 49');
});

it('does not collect when disabled', function () {
    Config::set('logging.channels.github.tracing.breadcrumbs', false);

    $this->collector->handleMessageLogged(new MessageLogged('info', 'Should not be collected', []));

    expect(BreadcrumbCollector::getBreadcrumbs())->toBeEmpty();
});

it('pushes breadcrumbs to context on collect', function () {
    $this->collector->handleMessageLogged(new MessageLogged('info', 'Test message', []));

    expect(Context::hasHidden('breadcrumbs'))->toBeFalse();

    $this->collector->collect();

    $contextBreadcrumbs = Context::getHidden('breadcrumbs');
    expect($contextBreadcrumbs)->toHaveCount(1);
    expect($contextBreadcrumbs[0]['message'])->toBe('[info] Test message');
});

it('does not push to context when no breadcrumbs exist', function () {
    $this->collector->collect();

    expect(Context::hasHidden('breadcrumbs'))->toBeFalse();
});

it('preserves chronological order of mixed event types', function () {
    $this->collector->handleMessageLogged(new MessageLogged('info', 'First log', []));

    $cacheHit = new CacheHit('array', 'key1', 'value');
    $this->collector->handleCacheHit($cacheHit);

    $this->collector->handleMessageLogged(new MessageLogged('warning', 'Second log', []));

    $cacheMiss = new CacheMissed('array', 'key2');
    $this->collector->handleCacheMissed($cacheMiss);

    $breadcrumbs = BreadcrumbCollector::getBreadcrumbs();

    expect($breadcrumbs)->toHaveCount(4);
    expect($breadcrumbs[0]['category'])->toBe('log');
    expect($breadcrumbs[1]['category'])->toBe('cache');
    expect($breadcrumbs[2]['category'])->toBe('log');
    expect($breadcrumbs[3]['category'])->toBe('cache');
});

it('includes timestamp in each breadcrumb', function () {
    $this->collector->handleMessageLogged(new MessageLogged('info', 'Test', []));

    $breadcrumbs = BreadcrumbCollector::getBreadcrumbs();

    expect($breadcrumbs[0]['timestamp'])->toMatch('/^\d{2}:\d{2}:\d{2}\.\d{3}$/');
});
