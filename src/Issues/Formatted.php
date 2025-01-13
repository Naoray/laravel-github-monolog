<?php

namespace Naoray\LaravelGithubMonolog\Issues;

class Formatted
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $comment,
    ) {}
}
