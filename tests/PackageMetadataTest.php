<?php

test('it requires the openssl extension', function () {
    $composer = json_decode(
        file_get_contents(__DIR__.'/../composer.json') ?: throw new RuntimeException('Unable to read composer.json.'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($composer['require']['ext-openssl'] ?? null)->toBe('*');
});
