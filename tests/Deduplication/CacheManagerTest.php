<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Naoray\LaravelGithubMonolog\Deduplication\CacheManager;
use function Pest\Laravel\travel;

beforeEach(function () {
    $this->store = 'array';
    $this->prefix = 'test:';
    $this->ttl = 60;

    $this->manager = new CacheManager(
        store: $this->store,
        prefix: $this->prefix,
        ttl: $this->ttl
    );

    Cache::store($this->store)->clear();
});

afterEach(function () {
    Carbon::setTestNow();
    Cache::store($this->store)->clear();
});

test('it can add and check signatures', function () {
    $signature = 'test-signature';

    expect($this->manager->has($signature))->toBeFalse();

    $this->manager->add($signature);

    expect($this->manager->has($signature))->toBeTrue();
});

test('it expires old entries', function () {
    $signature = 'test-signature';
    $this->manager->add($signature);

    // Travel forward in time past TTL
    travel($this->ttl + 1)->seconds();

    expect($this->manager->has($signature))->toBeFalse();
});

test('it keeps valid entries', function () {
    $signature = 'test-signature';
    $this->manager->add($signature);

    // Travel forward in time but not past TTL
    travel($this->ttl - 1)->seconds();

    expect($this->manager->has($signature))->toBeTrue();
});
