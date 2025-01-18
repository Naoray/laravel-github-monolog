<?php

use Naoray\LaravelGithubMonolog\Issues\Formatters\StackTraceFormatter;

beforeEach(function () {
    $this->formatter = new StackTraceFormatter();
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
