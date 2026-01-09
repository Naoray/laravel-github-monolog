<?php

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\UserDataCollector;

beforeEach(function () {
    $this->collector = new UserDataCollector;
});

afterEach(function () {
    // Reset to default resolver
    UserDataCollector::setUserDataResolver(null);
    Context::flush();
});

it('collects default user data', function () {
    // Arrange
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->once()->andReturn(1);
    $event = new Authenticated('web', $user);

    // Act
    ($this->collector)($event);

    // Assert
    $userData = Context::get('user');
    expect($userData)->toHaveKey('id');
    expect($userData)->toHaveKey('authenticated');
    expect($userData['id'])->toBe(1);
    expect($userData['authenticated'])->toBeTrue();
});

it('uses custom user data resolver', function () {
    // Arrange
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->never();
    $event = new Authenticated('web', $user);

    UserDataCollector::setUserDataResolver(fn ($user) => ['custom' => 'data']);

    // Act
    ($this->collector)($event);

    // Assert
    expect(Context::get('user'))->toBe(['custom' => 'data']);
});

it('collects user data on demand when not authenticated', function () {
    Auth::shouldReceive('check')->once()->andReturn(false);

    $this->collector->collect();

    $userData = Context::get('user');
    expect($userData)->toBe(['authenticated' => false]);
});
