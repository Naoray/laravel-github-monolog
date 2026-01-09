<?php

use Naoray\LaravelGithubMonolog\Issues\SectionMapping;

test('it returns all sections when no replacements provided', function () {
    $sections = SectionMapping::getSectionsToRemove([]);

    expect($sections)->toContain('stacktrace')
        ->toContain('prev-exception')
        ->toContain('context')
        ->toContain('extra')
        ->toContain('prev-exception-stacktrace')
        ->toContain('environment')
        ->toContain('request')
        ->toContain('route')
        ->toContain('user')
        ->toContain('queries')
        ->toContain('job')
        ->toContain('command')
        ->toContain('outgoing_requests')
        ->toContain('session');
});

test('it returns empty sections based on empty replacements', function () {
    $replacements = [
        '{simplified_stack_trace}' => 'some content',
        '{context}' => '',
        '{extra}' => 'more content',
    ];

    $sections = SectionMapping::getSectionsToRemove($replacements);

    expect($sections)->toBe(['context']);
});

test('it returns remaining sections after removing empty ones', function () {
    $sectionsToRemove = ['stacktrace', 'extra'];

    $remaining = SectionMapping::getRemainingSections($sectionsToRemove);

    expect($remaining)->toContain('prev-exception')
        ->toContain('context')
        ->toContain('prev-exception-stacktrace')
        ->toContain('environment')
        ->toContain('request')
        ->toContain('route')
        ->toContain('user')
        ->toContain('queries')
        ->toContain('job')
        ->toContain('command')
        ->toContain('outgoing_requests')
        ->toContain('session');
});

test('it returns correct pattern for removing section content', function () {
    $pattern = SectionMapping::getSectionPattern('test', true);

    expect($pattern)->toBe("/<!-- test:start -->.*?<!-- test:end -->\n?/s");
});

test('it returns correct pattern for preserving section content', function () {
    $pattern = SectionMapping::getSectionPattern('test');

    expect($pattern)->toBe('/<!-- test:start -->\s*(.*?)\s*<!-- test:end -->/s');
});

test('it returns correct pattern for standalone flags', function () {
    $pattern = SectionMapping::getStandaloneFlagPattern();

    expect($pattern)->toContain('environment')
        ->toContain('request')
        ->toContain('route')
        ->toContain('user')
        ->toContain('queries')
        ->toContain('job')
        ->toContain('command')
        ->toContain('outgoing_requests')
        ->toContain('session');
});

test('it handles new section placeholders correctly', function () {
    $replacements = [
        '{environment}' => '',
        '{request}' => 'some content',
        '{route}' => '',
        '{user}' => '',
        '{queries}' => '',
    ];

    $sections = SectionMapping::getSectionsToRemove($replacements);

    expect($sections)->toContain('environment')
        ->toContain('route')
        ->toContain('user')
        ->toContain('queries')
        ->not->toContain('request');
});
