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
    File::shouldReceive('exists')
        ->with($publishedPath)
        ->andReturn(true);
    File::shouldReceive('get')
        ->with($publishedPath)
        ->andReturn('published content');

    expect($this->loader->load('issue'))->toBe('published content');
});

test('it falls back to package stub if published stub does not exist', function () {
    $publishedPath = resource_path('views/vendor/github-monolog/issue.md');
    $packagePath = __DIR__.'/../../resources/views/issue.md';
    $expectedContent = <<<'MD'
**Log Level:** {level}

{message}

**Simplified Stack Trace:**
```php
{simplified_stack_trace}
```

<details>
<summary>Complete Stack Trace</summary>

```php
{full_stack_trace}
```
</details>

<details>
<summary>Previous Exceptions</summary>

{previous_exceptions}
</details>

{context}

{extra}

<!-- Signature: {signature} -->

MD;

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
