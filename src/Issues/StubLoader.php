<?php

namespace Naoray\LaravelGithubMonolog\Issues;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

class StubLoader
{
    public function load(string $name): string
    {
        $publishedPath = resource_path("views/vendor/github-monolog/{$name}.md");
        $packagePath = __DIR__ . "/../../resources/views/{$name}.md";

        if (File::exists($publishedPath)) {
            return (string) File::get($publishedPath);
        }

        if (!File::exists($packagePath)) {
            throw new FileNotFoundException("Package stub not found: {$packagePath}");
        }

        return (string) File::get($packagePath);
    }
}
