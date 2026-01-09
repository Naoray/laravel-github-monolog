<?php

use Naoray\LaravelGithubMonolog\Issues\Formatters\ExtraFormatter;

beforeEach(function () {
    $this->formatter = new ExtraFormatter;
});

it('returns empty string for empty extra', function () {
    expect($this->formatter->format([]))->toBe('');
});

it('formats extra data as JSON', function () {
    $extra = [
        'key1' => 'value1',
        'key2' => ['nested' => 'data'],
    ];

    $result = $this->formatter->format($extra);

    expect($result)
        ->toContain('"key1": "value1"')
        ->toContain('"key2"')
        ->toContain('"nested": "data"');
});

it('formats complex extra data', function () {
    $extra = [
        'channel' => 'test',
        'level' => 400,
        'datetime' => '2024-01-01 12:00:00',
    ];

    $result = $this->formatter->format($extra);

    expect($result)
        ->toContain('"channel": "test"')
        ->toContain('"level": 400')
        ->toContain('"datetime": "2024-01-01 12:00:00"');
});
