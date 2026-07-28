<?php

namespace Naoray\LaravelGithubMonolog\Auth;

interface TokenProviderInterface
{
    /**
     * Resolve a GitHub API token to authenticate requests with.
     */
    public function getToken(): string;
}
