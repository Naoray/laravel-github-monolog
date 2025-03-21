<?php

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\UserDataCollector;

beforeEach(function () {
    $this->collector = new UserDataCollector;
});

afterEach(function () {
    // Reset to default resolver
    UserDataCollector::setUserDataResolver(fn (Authenticatable $user) => ['id' => $user->getAuthIdentifier()]);
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
    expect(Context::get('user'))->toBe(['id' => 1]);
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
