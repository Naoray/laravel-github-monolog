<?php

use Naoray\LaravelGithubMonolog\Issues\Formatters\QueryFormatter;

beforeEach(function () {
    $this->formatter = new QueryFormatter;
});

it('returns empty string for null queries', function () {
    expect($this->formatter->format(null))->toBe('');
});

it('returns empty string for empty array', function () {
    expect($this->formatter->format([]))->toBe('');
});

it('formats single query correctly', function () {
    $queries = [
        [
            'sql' => 'SELECT * FROM users',
            'bindings' => [],
            'time' => 10.5,
            'connection' => 'mysql',
        ],
    ];

    $result = $this->formatter->format($queries);

    expect($result)
        ->toContain('```sql')
        ->toContain('SELECT * FROM users')
        ->toContain('Connection: mysql')
        ->toContain('10.5ms')
        ->toContain('```');
});

it('formats query with bindings', function () {
    $queries = [
        [
            'sql' => 'SELECT * FROM users WHERE id = ?',
            'bindings' => [1],
            'time' => 5.0,
            'connection' => 'mysql',
        ],
    ];

    $result = $this->formatter->format($queries);

    expect($result)
        ->toContain('SELECT * FROM users WHERE id = ?')
        ->toContain('Bindings:')
        ->toContain('1');
});

it('formats multiple queries correctly', function () {
    $queries = [
        [
            'sql' => 'SELECT * FROM users',
            'bindings' => [],
            'time' => 5.0,
            'connection' => 'mysql',
        ],
        [
            'sql' => 'UPDATE posts SET views = ?',
            'bindings' => [100],
            'time' => 12.3,
            'connection' => 'mysql',
        ],
    ];

    $result = $this->formatter->format($queries);

    expect($result)
        ->toContain('SELECT * FROM users')
        ->toContain('UPDATE posts SET views = ?')
        ->toContain('5.0ms')
        ->toContain('12.3ms');
});

it('uses default connection when not provided', function () {
    $queries = [
        [
            'sql' => 'SELECT * FROM users',
            'bindings' => [],
            'time' => 10.0,
        ],
    ];

    $result = $this->formatter->format($queries);

    expect($result)->toContain('Connection: default');
});
