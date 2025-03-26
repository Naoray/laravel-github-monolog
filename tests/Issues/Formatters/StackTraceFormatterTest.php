<?php

namespace Naoray\LaravelGithubMonolog\Tests\Issues\Formatters;

use Naoray\LaravelGithubMonolog\Issues\Formatters\StackTraceFormatter;

beforeEach(function () {
    $this->formatter = new StackTraceFormatter;
});

test('it formats stack trace', function () {
    $stackTrace = <<<'TRACE'
#0 /app/Http/Controllers/TestController.php(25): TestController->testMethod()
#1 /vendor/laravel/framework/src/Testing.php(50): VendorClass->vendorMethod()
#2 /vendor/another/package/src/File.php(100): AnotherVendorClass->anotherVendorMethod()
#3 /app/Services/TestService.php(30): TestService->serviceMethod()
TRACE;

    $formatted = $this->formatter->format($stackTrace);

    expect($formatted)
        ->toContain('/app/Http/Controllers/TestController.php')
        ->toContain('/app/Services/TestService.php')
        ->toContain('[Vendor frames]')
        ->not->toContain('/vendor/laravel/framework/src/Testing.php')
        ->not->toContain('/vendor/another/package/src/File.php');
});

test('it collapses consecutive vendor frames', function () {
    $stackTrace = <<<'TRACE'
#0 /vendor/package1/src/File1.php(10): Method1()
#1 /vendor/package1/src/File2.php(20): Method2()
#2 /vendor/package2/src/File3.php(30): Method3()
TRACE;

    $formatted = $this->formatter->format($stackTrace);

    expect($formatted)
        ->toContain('[Vendor frames]')
        ->not->toContain('/vendor/package1/src/File1.php')
        ->not->toContain('/vendor/package2/src/File3.php')
        // Should only appear once even though there are multiple vendor frames
        ->and(substr_count($formatted, '[Vendor frames]'))->toBe(1);
});

test('it preserves non-vendor frames', function () {
    $stackTrace = <<<'TRACE'
#0 /app/Http/Controllers/TestController.php(25): TestController->testMethod()
#1 /app/Services/TestService.php(30): TestService->serviceMethod()
TRACE;

    $formatted = $this->formatter->format($stackTrace);

    expect($formatted)
        ->toContain('/app/Http/Controllers/TestController.php')
        ->toContain('/app/Services/TestService.php')
        ->not->toContain('[Vendor frames]');
});

test('it replaces base path in stack traces', function () {
    $formatter = new StackTraceFormatter();
    $basePath = base_path();

    $stackTrace = <<<TRACE
#0 {$basePath}/app/Services/ImageService.php(25): Spatie\\Image\\Image->loadFile()
#1 {$basePath}/vendor/laravel/framework/src/Foundation/Application.php(1235): App\\Services\\ImageService->process()
#2 {$basePath}/artisan(13): Illuminate\\Foundation\\Application->run()
TRACE;

    // Test simplified trace (with vendor frame collapsing)
    expect($formatter->format($stackTrace, true))
        ->toBe(<<<TRACE
#00 /app/Services/ImageService.php(25): Spatie\\Image\\Image->loadFile()
[Vendor frames]
TRACE
        );

    // Test full trace (without vendor frame collapsing)
    expect($formatter->format($stackTrace, false))
        ->toBe(<<<TRACE
#00 /app/Services/ImageService.php(25): Spatie\\Image\\Image->loadFile()
#01 /vendor/laravel/framework/src/Foundation/Application.php(1235): App\\Services\\ImageService->process()
#02 /artisan(13): Illuminate\\Foundation\\Application->run()
TRACE
        );
});

test('it handles empty base path correctly', function () {
    $formatter = new StackTraceFormatter();

    $stackTrace = <<<TRACE
#0 /absolute/path/to/app/Services/ImageService.php(25): someMethod()
#1 /vendor/package/src/File.php(10): otherMethod()
TRACE;

    $result = $formatter->format($stackTrace, true);

    expect($result)
        ->toBe(<<<TRACE
#00 /absolute/path/to/app/Services/ImageService.php(25): someMethod()
[Vendor frames]
TRACE
        );
});

test('it preserves non-stack-trace lines', function () {
    $formatter = new StackTraceFormatter();

    $stackTrace = <<<TRACE
[2024-03-21 12:00:00] Error: Something went wrong
#0 /app/Services/Service.php(25): someMethod()
#1 /vendor/package/src/File.php(10): otherMethod()
TRACE;

    $result = $formatter->format($stackTrace, true);

    expect($result)
        ->toBe(<<<TRACE
[2024-03-21 12:00:00] Error: Something went wrong
#00 /app/Services/Service.php(25): someMethod()
[Vendor frames]
TRACE
        );
});

test('it recognizes artisan lines as vendor frames', function () {
    $formatter = new StackTraceFormatter();
    $basePath = base_path();

    $stackTrace = <<<TRACE
#0 {$basePath}/app/Console/Commands/ImportCommand.php(25): handle()
#1 {$basePath}/artisan(13): Illuminate\\Foundation\\Application->run()
#2 {$basePath}/artisan(37): require()
TRACE;

    // Test simplified trace (with vendor frame collapsing)
    expect($formatter->format($stackTrace, true))
        ->toBe(<<<TRACE
#00 /app/Console/Commands/ImportCommand.php(25): handle()
[Vendor frames]
TRACE
        );

    // Test that artisan lines are preserved in full trace
    expect($formatter->format($stackTrace, false))
        ->toBe(<<<TRACE
#00 /app/Console/Commands/ImportCommand.php(25): handle()
#01 /artisan(13): Illuminate\\Foundation\\Application->run()
#02 /artisan(37): require()
TRACE
        );
});

test('it collapses multiple artisan lines into single vendor frame', function () {
    $formatter = new StackTraceFormatter();

    $stackTrace = <<<TRACE
#0 /artisan(13): Illuminate\\Foundation\\Application->run()
#1 /vendor/laravel/framework/src/Foundation/Application.php(1235): handle()
#2 /artisan(37): require()
TRACE;

    expect($formatter->format($stackTrace, true))
        ->toBe('[Vendor frames]');
});
