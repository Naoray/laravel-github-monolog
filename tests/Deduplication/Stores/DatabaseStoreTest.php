<?php

namespace Naoray\LaravelGithubMonolog\Tests\DeduplicationStores;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Naoray\LaravelGithubMonolog\DeduplicationStores\DatabaseDeduplicationStore;
use PHPUnit\Framework\Attributes\Test;

class DatabaseDeduplicationStoreTest extends AbstractDeduplicationStoreTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we're using SQLite for testing
        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('github_monolog_deduplication');
        Schema::dropIfExists('custom_dedup');
        parent::tearDown();
    }

    protected function createStore(string $prefix = 'test:', int $time = 60): DatabaseDeduplicationStore
    {
        return new DatabaseDeduplicationStore(
            connection: 'sqlite',
            table: 'github_monolog_deduplication',
            prefix: $prefix,
            time: $time
        );
    }

    #[Test]
    public function it_creates_table_if_not_exists(): void
    {
        $this->createStore();

        $this->assertTrue(
            Schema::hasTable('github_monolog_deduplication')
        );
    }

    #[Test]
    public function it_can_use_custom_table(): void
    {
        $store = new DatabaseDeduplicationStore(
            connection: 'sqlite',
            table: 'custom_dedup',
            prefix: 'test:',
            time: 60
        );

        $record = $this->createLogRecord();
        $store->add($record, 'test-signature');

        $this->assertTrue(Schema::hasTable('custom_dedup'));
        $this->assertCount(1, DB::table('custom_dedup')->get());
    }

    #[Test]
    public function it_cleans_up_expired_entries_from_database(): void
    {
        $store = $this->createStore(time: 1);
        $record = $this->createLogRecord();

        $store->add($record, 'test-signature');
        $this->assertCount(1, DB::table('github_monolog_deduplication')->get());

        sleep(2);
        $store->get(); // Trigger cleanup

        $this->assertCount(0, DB::table('github_monolog_deduplication')->get());
    }

    #[Test]
    public function it_only_returns_entries_for_specific_prefix(): void
    {
        $store1 = $this->createStore('prefix1:');
        $store2 = $this->createStore('prefix2:');
        $record = $this->createLogRecord();

        $store1->add($record, 'signature1');
        $store2->add($record, 'signature2');

        $this->assertCount(1, $store1->get());
        $this->assertCount(1, $store2->get());
    }
}
