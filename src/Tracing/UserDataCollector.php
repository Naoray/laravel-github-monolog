<?php

namespace Naoray\LaravelGithubMonolog\Tracing;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\Concerns\ResolvesTracingConfig;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\DataCollectorInterface;
use Naoray\LaravelGithubMonolog\Tracing\Contracts\EventDrivenCollectorInterface;
use Throwable;

class UserDataCollector implements DataCollectorInterface, EventDrivenCollectorInterface
{
    use ResolvesTracingConfig;

    private static $userDataResolver = null;

    /**
     * Remember the user even after logout for exception context.
     */
    private static ?Authenticatable $rememberedUser = null;

    /**
     * Cache resolved user details to avoid repeated resolution.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $resolvedDetails = null;

    public static function setUserDataResolver(?callable $resolver): void
    {
        self::$userDataResolver = $resolver;
    }

    /**
     * Remember a user for exception context (useful before logout).
     */
    public static function rememberUser(?Authenticatable $user): void
    {
        self::$rememberedUser = $user;
        self::$resolvedDetails = null;
    }

    /**
     * Flush cached user data (useful between requests in long-running processes).
     */
    public static function flush(): void
    {
        self::$rememberedUser = null;
        self::$resolvedDetails = null;
    }

    public function isEnabled(): bool
    {
        return $this->isTracingFeatureEnabled('user');
    }

    /**
     * Handle authentication event.
     */
    public function __invoke(Authenticated $event): void
    {
        self::rememberUser($event->user);
        $this->addUserToContext($event->user);
    }

    /**
     * Handle logout event - remember user before they're gone.
     */
    public function handleLogout(Logout $event): void
    {
        self::rememberUser($event->user);
    }

    /**
     * Collect user data on-demand (e.g., when exception occurs).
     */
    public function collect(): void
    {
        // First, try to get user from Auth (if guards have been resolved)
        $user = $this->tryGetAuthenticatedUser();
        if ($user !== null) {
            $this->addUserToContext($user);

            return;
        }

        // Fall back to remembered user (e.g., user who just logged out)
        if (self::$rememberedUser !== null) {
            $this->addUserToContext(self::$rememberedUser);

            return;
        }

        // No user available
        Context::add('user', ['authenticated' => false]);
    }

    /**
     * Try to get the authenticated user from Auth facade.
     */
    private function tryGetAuthenticatedUser(): ?Authenticatable
    {
        try {
            if (Auth::check()) {
                return Auth::user();
            }
        } catch (Throwable) {
            // Auth may throw during bootstrap
        }

        return null;
    }

    /**
     * Resolve user details with caching.
     *
     * @return array<string, mixed>
     */
    protected function resolveUserDetails(Authenticatable $user): array
    {
        // Return cached details if available
        if (self::$resolvedDetails !== null) {
            return self::$resolvedDetails;
        }

        try {
            $id = $user->getAuthIdentifier();
        } catch (Throwable) {
            return self::$resolvedDetails = ['authenticated' => true, 'id' => null];
        }

        $resolver = self::$userDataResolver;

        if ($resolver === null) {
            return self::$resolvedDetails = [
                'id' => $id,
                'authenticated' => true,
                'name' => $user->name ?? null,
                'email' => $user->email ?? null,
            ];
        }

        try {
            $customData = $resolver($user);

            return self::$resolvedDetails = [
                'id' => $id,
                'authenticated' => true,
                ...$customData,
            ];
        } catch (Throwable) {
            return self::$resolvedDetails = ['authenticated' => true, 'id' => $id];
        }
    }

    /**
     * Add user data to context.
     */
    protected function addUserToContext(Authenticatable $user): void
    {
        Context::add('user', $this->resolveUserDetails($user));
    }

    public function getUserDataResolver(): callable
    {
        return self::$userDataResolver ?? fn (Authenticatable $user) => [
            'id' => $user->getAuthIdentifier(),
            'authenticated' => true,
            'name' => $user->name ?? null,
            'email' => $user->email ?? null,
        ];
    }
}
