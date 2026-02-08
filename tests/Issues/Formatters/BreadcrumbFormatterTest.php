<?php

use Naoray\LaravelGithubMonolog\Issues\Formatters\BreadcrumbFormatter;

beforeEach(function () {
    $this->formatter = new BreadcrumbFormatter;
});

it('returns empty string for null breadcrumbs', function () {
    expect($this->formatter->format(null))->toBe('');
});

it('returns empty string for empty array', function () {
    expect($this->formatter->format([]))->toBe('');
});

it('formats single breadcrumb as markdown table', function () {
    $breadcrumbs = [
        [
            'timestamp' => '14:30:15.123',
            'category' => 'log',
            'message' => '[info] User logged in',
            'metadata' => [],
        ],
    ];

    $result = $this->formatter->format($breadcrumbs);

    expect($result)
        ->toContain('| Time | Category | Message | Details |')
        ->toContain('| --- | --- | --- | --- |')
        ->toContain('| 14:30:15.123 | log | [info] User logged in |  |');
});

it('formats breadcrumb with metadata', function () {
    $breadcrumbs = [
        [
            'timestamp' => '14:30:15.123',
            'category' => 'cache',
            'message' => 'Cache hit: user.123',
            'metadata' => ['store' => 'redis'],
        ],
    ];

    $result = $this->formatter->format($breadcrumbs);

    expect($result)
        ->toContain('| 14:30:15.123 | cache | Cache hit: user.123 | store: redis |');
});

it('formats multiple breadcrumbs correctly', function () {
    $breadcrumbs = [
        [
            'timestamp' => '14:30:15.100',
            'category' => 'log',
            'message' => '[info] Request started',
            'metadata' => [],
        ],
        [
            'timestamp' => '14:30:15.200',
            'category' => 'cache',
            'message' => 'Cache miss: config',
            'metadata' => ['store' => 'array'],
        ],
        [
            'timestamp' => '14:30:15.300',
            'category' => 'log',
            'message' => '[warning] Slow query detected',
            'metadata' => [],
        ],
    ];

    $result = $this->formatter->format($breadcrumbs);

    $lines = explode("\n", $result);

    // Header + separator + 3 data rows
    expect($lines)->toHaveCount(5);
    expect($lines[2])->toContain('Request started');
    expect($lines[3])->toContain('Cache miss: config');
    expect($lines[4])->toContain('Slow query detected');
});

it('escapes pipe characters in messages', function () {
    $breadcrumbs = [
        [
            'timestamp' => '14:30:15.123',
            'category' => 'log',
            'message' => '[info] Value is A|B|C',
            'metadata' => [],
        ],
    ];

    $result = $this->formatter->format($breadcrumbs);

    expect($result)
        ->toContain('A\\|B\\|C')
        ->not->toMatch('/A\|B\|C[^\\\\]/');
});

it('formats multiple metadata entries', function () {
    $breadcrumbs = [
        [
            'timestamp' => '14:30:15.123',
            'category' => 'cache',
            'message' => 'Cache hit: key',
            'metadata' => ['store' => 'redis', 'ttl' => '3600'],
        ],
    ];

    $result = $this->formatter->format($breadcrumbs);

    expect($result)->toContain('store: redis, ttl: 3600');
});
