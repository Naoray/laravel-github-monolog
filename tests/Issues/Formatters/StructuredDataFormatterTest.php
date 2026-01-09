<?php

use Naoray\LaravelGithubMonolog\Issues\Formatters\StructuredDataFormatter;

beforeEach(function () {
    $this->formatter = new StructuredDataFormatter;
});

it('returns empty string for null data', function () {
    expect($this->formatter->format(null))->toBe('');
});

it('returns empty string for empty array', function () {
    expect($this->formatter->format([]))->toBe('');
});

it('formats array data as JSON code block', function () {
    $data = [
        'key' => 'value',
        'nested' => ['inner' => 'data'],
    ];

    $result = $this->formatter->format($data);

    expect($result)
        ->toContain('```json')
        ->toContain('"key": "value"')
        ->toContain('"nested"')
        ->toContain('"inner": "data"')
        ->toContain('```');
});
