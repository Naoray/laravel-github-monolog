<?php

namespace Naoray\LaravelGithubMonolog\Auth;

class PersonalAccessTokenProvider implements TokenProviderInterface
{
    public function __construct(
        private readonly string $token,
    ) {}

    public function getToken(): string
    {
        return $this->token;
    }
}
