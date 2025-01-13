<?php

namespace Naoray\LaravelGithubMonolog\Tests\DeduplicationStores;

use Illuminate\Support\Facades\Redis;
use Naoray\LaravelGithubMonolog\DeduplicationStores\RedisDeduplicationStore;
use PHPUnit\Framework\Attributes\Test;

class RedisDeduplicationStoreTest extends AbstractDeduplicationStoreTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configure Redis for testing
        $this->app['config']->set('database.redis.default', [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379),
            'database' => 0,
        ]);

        // Configure second Redis connection for testing
        $this->app['config']->set('database.redis.other', [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379),
            'database' => 1,
        ]);

        // Clear test keys
        Redis::del('test:dedup');
        Redis::del('custom:dedup');
        Redis::connection('other')->del('test:dedup');
    }

    protected function tearDown(): void
    {
        Redis::del('test:dedup');
        Redis::del('custom:dedup');
        Redis::connection('other')->del('test:dedup');
        parent::tearDown();
    }

    protected function createStore(string $prefix = 'test:', int $time = 60): RedisDeduplicationStore
    {
        return new RedisDeduplicationStore(
            connection: 'default',
            prefix: $prefix,
            time: $time
        );
    }

    #[Test]
    public function it_uses_correct_redis_key(): void
    {
        $store = $this->createStore('custom:');
        $record = $this->createLogRecord();

        $store->add($record, 'test-signature');

        $this->assertGreaterThan(0, Redis::exists('custom:dedup'));
    }

    #[Test]
    public function it_can_use_different_redis_connection(): void
    {
        $store = new RedisDeduplicationStore(
            connection: 'other',
            prefix: 'test:',
            time: 60
        );

        $record = $this->createLogRecord();
        $store->add($record, 'test-signature');

        $this->assertGreaterThan(0, Redis::connection('other')->exists('test:dedup'));
    }

    #[Test]
    public function it_properly_cleans_up_expired_entries(): void
    {
        $store = $this->createStore(time: 1);
        $record = $this->createLogRecord();

        // Add an entry that will expire
        $store->add($record, 'test-signature');

        // Verify it exists
        $this->assertCount(1, $store->get());

        // Wait for expiration
        sleep(2);

        // Get entries (should trigger cleanup)
        $entries = $store->get();

        // Verify Redis directly
        $this->assertEquals(
            0,
            Redis::zcount($store->getKey(), '-inf', '+inf'),
            'Redis should have no entries after cleanup'
        );
    }
}
