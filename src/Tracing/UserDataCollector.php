<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\DataCollectorInterface;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;

class UserDataCollector implements DataCollectorInterface, EventDrivenCollectorInterface
{
    private static $userDataResolver = null;

    public static function setUserDataResolver(?callable $resolver): void
    {
        self::$userDataResolver = $resolver;
    }

    public function isEnabled(): bool
    {
        $config = config('logging.channels.github.tracing', []);

        return isset($config['user']) && $config['user'];
    }

    public function __invoke(Authenticated $event): void
    {
        Context::add(
            'user',
            $this->getUserDataResolver()($event->user)
        );
    }

    /**
     * Collect user data on-demand (e.g., when exception occurs).
     */
    public function collect(): void
    {
        if (! Auth::check()) {
            Context::add('user', ['authenticated' => false]);

            return;
        }

        $user = Auth::user();

        Context::add(
            'user',
            $this->getUserDataResolver()($user)
        );
    }

    public function getUserDataResolver(): callable
    {
        return self::$userDataResolver ?? function (Authenticatable $user) {
            $data = [
                'id' => $user->getAuthIdentifier(),
                'authenticated' => true,
            ];

            return $data;
        };
    }
}
