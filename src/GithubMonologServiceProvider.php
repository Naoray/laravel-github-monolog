<?php

namespace Naoray\LaravelGithubMonolog;

use Illuminate\Support\ServiceProvider;
use Naoray\LaravelGithubMonolog\Deduplication\DefaultSignatureGenerator;
use Naoray\LaravelGithubMonolog\Deduplication\SignatureGeneratorInterface;

class GithubMonologServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SignatureGeneratorInterface::class, DefaultSignatureGenerator::class);
    }
}
