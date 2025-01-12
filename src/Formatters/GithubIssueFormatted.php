<?php

namespace Naoray\LaravelGithubMonolog\Formatters;

class GithubIssueFormatted
{
    public function __construct(
        public readonly string $signature,
        public readonly string $title,
        public readonly string $body,
        public readonly string $comment,
    ) {}
}
