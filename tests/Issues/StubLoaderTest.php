<?php

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Naoray\LaravelGithubMonolog\Issues\StubLoader;

beforeEach(function () {
    $this->loader = new StubLoader;
    File::partialMock();
});

test('it loads stub from published path if it exists', function () {
    $publishedPath = resource_path('views/vendor/github-monolog/issue.md');
    $packagePath = __DIR__.'/../../resources/views/issue.md';
    $expectedContent = file_get_contents($packagePath);

    File::shouldReceive('exists')
        ->with($publishedPath)
        ->andReturn(true);
    File::shouldReceive('get')
        ->with($publishedPath)
        ->andReturn($expectedContent);

    expect($this->loader->load('issue'))->toBe($expectedContent);
});

test('it falls back to package stub if published stub does not exist', function () {
    $publishedPath = resource_path('views/vendor/github-monolog/issue.md');
    $packagePath = __DIR__.'/../../resources/views/issue.md';
    $expectedContent = file_get_contents($packagePath);

    File::shouldReceive('exists')
        ->with($publishedPath)
        ->andReturn(false);
    File::shouldReceive('exists')
        ->with($packagePath)
        ->andReturn(true);
    File::shouldReceive('get')
        ->with($packagePath)
        ->andReturn($expectedContent);

    expect($this->loader->load('issue'))->toBe($expectedContent);
});

test('it throws exception if stub does not exist', function () {
    $publishedPath = resource_path('views/vendor/github-monolog/nonexistent.md');
    $packagePath = __DIR__.'/../../resources/views/nonexistent.md';

    File::shouldReceive('exists')
        ->with($publishedPath)
        ->andReturn(false);
    File::shouldReceive('exists')
        ->with($packagePath)
        ->andReturn(false);

    expect(fn () => $this->loader->load('nonexistent'))
        ->toThrow(FileNotFoundException::class);
});
