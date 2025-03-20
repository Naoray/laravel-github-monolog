<?php

use Naoray\LaravelGithubMonolog\Issues\SectionMapping;

test('it returns all sections when no replacements provided', function () {
    $sections = SectionMapping::getSectionsToRemove([]);

    expect($sections)->toBe([
        'stacktrace',
        'prev-exception',
        'context',
        'extra',
        'prev-exception-stacktrace',
    ]);
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

    expect($remaining)->toBe(['prev-exception', 'context', 'prev-exception-stacktrace']);
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

    expect($pattern)->toBe('/<!-- (stacktrace|prev-stacktrace|context|extra|prev-exception|prev-exception-stacktrace):(start|end) -->\n?/s');
});
