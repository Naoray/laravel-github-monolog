<?php

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\UserDataCollector;

beforeEach(function () {
    $this->collector = new UserDataCollector;
    UserDataCollector::flush();
});

afterEach(function () {
    // Reset to default resolver
    UserDataCollector::setUserDataResolver(null);
    UserDataCollector::flush();
    Context::flush();
});

it('collects default user data', function () {
    // Arrange
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(1);
    $user->name = 'John Doe';
    $user->email = 'john@example.com';
    $event = new Authenticated('web', $user);

    // Act
    ($this->collector)($event);

    // Assert
    $userData = Context::get('user');
    expect($userData)->toHaveKey('id');
    expect($userData)->toHaveKey('authenticated');
    expect($userData['id'])->toBe(1);
    expect($userData['authenticated'])->toBeTrue();
    expect($userData['name'])->toBe('John Doe');
    expect($userData['email'])->toBe('john@example.com');
});

it('uses custom user data resolver', function () {
    // Arrange
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(1);
    $event = new Authenticated('web', $user);

    UserDataCollector::setUserDataResolver(fn ($user) => ['custom' => 'data']);

    // Act
    ($this->collector)($event);

    // Assert
    $userData = Context::get('user');
    expect($userData)->toHaveKey('custom');
    expect($userData['custom'])->toBe('data');
    expect($userData)->toHaveKey('id');
    expect($userData['id'])->toBe(1);
});

it('collects user data on demand when not authenticated', function () {
    Auth::shouldReceive('check')->once()->andReturn(false);

    $this->collector->collect();

    $userData = Context::get('user');
    expect($userData)->toBe(['authenticated' => false]);
});

it('remembers user on authenticated event', function () {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(42);
    $user->name = null;
    $user->email = null;

    $event = new Authenticated('web', $user);
    ($this->collector)($event);

    // Verify user is remembered
    $userData = Context::get('user');
    expect($userData['id'])->toBe(42);
});

it('remembers user on logout for exception context', function () {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(99);
    $user->name = 'Logging Out User';
    $user->email = 'logout@example.com';

    $event = new Logout('web', $user);
    $this->collector->handleLogout($event);

    // Now when we collect, it should use the remembered user
    Auth::shouldReceive('check')->andReturn(false);
    Context::flush();
    $this->collector->collect();

    $userData = Context::get('user');
    expect($userData['id'])->toBe(99);
    expect($userData['name'])->toBe('Logging Out User');
});

it('flushes remembered user data', function () {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(1);
    $user->name = 'Test';
    $user->email = 'test@example.com';

    UserDataCollector::rememberUser($user);
    UserDataCollector::flush();

    // After flush, should not have remembered user
    Auth::shouldReceive('check')->andReturn(false);
    $this->collector->collect();

    $userData = Context::get('user');
    expect($userData)->toBe(['authenticated' => false]);
});

it('handles exceptions during user resolution gracefully', function () {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andThrow(new Exception('Database error'));

    UserDataCollector::rememberUser($user);
    Auth::shouldReceive('check')->andReturn(false);
    $this->collector->collect();

    $userData = Context::get('user');
    expect($userData)->toHaveKey('authenticated');
    expect($userData['authenticated'])->toBeTrue();
    expect($userData['id'])->toBeNull();
});

it('caches resolved user details', function () {
    $user = Mockery::mock(Authenticatable::class);
    // getAuthIdentifier should only be called once due to caching
    $user->shouldReceive('getAuthIdentifier')->once()->andReturn(1);
    $user->name = 'Cached User';
    $user->email = 'cached@example.com';

    $event = new Authenticated('web', $user);
    ($this->collector)($event);

    // Second call should use cached data
    Context::flush();
    Auth::shouldReceive('check')->andReturn(false);
    $this->collector->collect();

    $userData = Context::get('user');
    expect($userData['id'])->toBe(1);
});
