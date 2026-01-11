<?php

namespace Naoray\LaravelGithubMonolog\Tests\Deduplication;

use Naoray\LaravelGithubMonolog\Deduplication\VendorFrameDetector;

beforeEach(function () {
    $this->detector = new VendorFrameDetector;
});

test('it detects vendor frames from file path', function () {
    $frame = [
        'file' => '/path/to/vendor/laravel/framework/src/File.php',
        'line' => 123,
        'function' => 'someMethod',
    ];

    expect($this->detector->isVendorFrame($frame))->toBeTrue();
});

test('it detects vendor frames from artisan path', function () {
    $frame = [
        'file' => '/path/to/artisan',
        'line' => 13,
        'function' => 'run',
    ];

    expect($this->detector->isVendorFrame($frame))->toBeTrue();
});

test('it detects vendor frames from main function', function () {
    $frame = [
        'file' => '/path/to/index.php',
        'line' => 1,
        'function' => '{main}',
    ];

    expect($this->detector->isVendorFrame($frame))->toBeTrue();
});

test('it does not detect app frames as vendor frames', function () {
    $frame = [
        'file' => '/path/to/app/Services/Service.php',
        'line' => 25,
        'function' => 'handle',
    ];

    expect($this->detector->isVendorFrame($frame))->toBeFalse();
});

test('it does not detect BoundMethod calling app code as vendor frame', function () {
    $frame = [
        'file' => '/path/to/vendor/laravel/framework/src/Support/BoundMethod.php',
        'line' => 123,
        'class' => 'Illuminate\\Support\\BoundMethod',
        'type' => '::',
        'function' => 'App\\Services\\Service::handle',
    ];

    expect($this->detector->isVendorFrame($frame))->toBeFalse();
});

test('it detects BoundMethod not calling app code as vendor frame', function () {
    $frame = [
        'file' => '/path/to/vendor/laravel/framework/src/Support/BoundMethod.php',
        'line' => 123,
        'class' => 'Illuminate\\Support\\BoundMethod',
        'type' => '::',
        'function' => 'Vendor\\Class::method',
    ];

    expect($this->detector->isVendorFrame($frame))->toBeTrue();
});

test('it handles frames without file', function () {
    $frame = [
        'line' => 123,
        'function' => 'someMethod',
    ];

    expect($this->detector->isVendorFrame($frame))->toBeFalse();
});

test('it detects vendor frames from stack trace line', function () {
    $line = '#0 /path/to/vendor/laravel/framework/src/File.php(123): SomeClass->method()';

    expect($this->detector->isVendorFrameLine($line))->toBeTrue();
});

test('it detects artisan frames from stack trace line', function () {
    $line = '#0 /path/to/artisan(13): Illuminate\\Foundation\\Application->run()';

    expect($this->detector->isVendorFrameLine($line))->toBeTrue();
});

test('it detects main function from stack trace line', function () {
    $line = '#105 {main}';

    expect($this->detector->isVendorFrameLine($line))->toBeTrue();
});

test('it does not detect app frames from stack trace line', function () {
    $line = '#0 /path/to/app/Services/Service.php(25): App\\Services\\Service->handle()';

    expect($this->detector->isVendorFrameLine($line))->toBeFalse();
});

test('it does not detect BoundMethod calling app code from stack trace line', function () {
    $line = '#0 /path/to/vendor/laravel/framework/src/Support/BoundMethod.php(123): App\\Services\\Service::handle()';

    expect($this->detector->isVendorFrameLine($line))->toBeFalse();
});

test('it detects BoundMethod not calling app code from stack trace line', function () {
    $line = '#0 /path/to/vendor/laravel/framework/src/Support/BoundMethod.php(123): Vendor\\Class::method()';

    expect($this->detector->isVendorFrameLine($line))->toBeTrue();
});
