<?php

use Naoray\LaravelGithubMonolog\Issues\Formatters\OutgoingRequestFormatter;

beforeEach(function () {
    $this->formatter = new OutgoingRequestFormatter;
});

it('returns empty string for null requests', function () {
    expect($this->formatter->format(null))->toBe('');
});

it('returns empty string for empty array', function () {
    expect($this->formatter->format([]))->toBe('');
});

it('formats single request correctly', function () {
    $requests = [
        [
            'method' => 'GET',
            'url' => 'https://api.example.com/users',
            'status' => 200,
            'duration_ms' => 150.5,
        ],
    ];

    $result = $this->formatter->format($requests);

    expect($result)
        ->toContain('```')
        ->toContain('GET https://api.example.com/users')
        ->toContain('→ 200')
        ->toContain('150.5ms');
});

it('formats request without status', function () {
    $requests = [
        [
            'method' => 'POST',
            'url' => 'https://api.example.com/posts',
            'duration_ms' => 100.0,
        ],
    ];

    $result = $this->formatter->format($requests);

    expect($result)
        ->toContain('POST https://api.example.com/posts')
        ->toContain('100ms')
        ->not->toContain('→');
});

it('formats request without duration', function () {
    $requests = [
        [
            'method' => 'GET',
            'url' => 'https://api.example.com/test',
            'status' => 200,
        ],
    ];

    $result = $this->formatter->format($requests);

    expect($result)
        ->toContain('GET https://api.example.com/test')
        ->toContain('→ 200')
        ->not->toContain('ms');
});

it('formats multiple requests correctly', function () {
    $requests = [
        [
            'method' => 'GET',
            'url' => 'https://api.example.com/users',
            'status' => 200,
            'duration_ms' => 100.0,
        ],
        [
            'method' => 'POST',
            'url' => 'https://api.example.com/posts',
            'status' => 201,
            'duration_ms' => 250.5,
        ],
    ];

    $result = $this->formatter->format($requests);

    expect($result)
        ->toContain('GET https://api.example.com/users')
        ->toContain('POST https://api.example.com/posts')
        ->toContain('200')
        ->toContain('201')
        ->toContain('100')
        ->toContain('250.5');
});

it('uses default method when not provided', function () {
    $requests = [
        [
            'url' => 'https://api.example.com/test',
        ],
    ];

    $result = $this->formatter->format($requests);

    expect($result)->toContain('GET https://api.example.com/test');
});
