<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Context;

class UserDataCollector
{
    private static $userDataResolver = null;

    public static function setUserDataResolver(callable $resolver): void
    {
        self::$userDataResolver = $resolver;
    }

    public function __invoke(Authenticated $event): void
    {
        Context::add(
            'user',
            $this->getUserDataResolver()($event->user)
        );
    }

    public function getUserDataResolver(): ?callable
    {
        return self::$userDataResolver
            ?? fn (Authenticatable $user) => ['id' => $user->getAuthIdentifier()];
    }
}
