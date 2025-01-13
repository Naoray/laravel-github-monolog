<?php

namespace Naoray\LaravelGithubMonolog\Tests\DeduplicationStores;

use Monolog\Level;
use Monolog\LogRecord;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

abstract class AbstractDeduplicationStoreTest extends TestCase
{
    protected function createLogRecord(string $message = 'test', Level $level = Level::Error): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: $level,
            message: $message,
            context: [],
            extra: [],
        );
    }

    #[Test]
    public function it_can_add_and_retrieve_entries(): void
    {
        $store = $this->createStore();
        $record = $this->createLogRecord();

        $store->add($record, 'test-signature');
        $entries = $store->get();

        $this->assertCount(1, $entries);
        $this->assertStringEndsWith('test-signature', $entries[0]);
    }

    #[Test]
    public function it_removes_expired_entries(): void
    {
        $store = $this->createStore(time: 1); // 1 second expiry
        $record = $this->createLogRecord();

        $store->add($record, 'test-signature');
        sleep(2); // Wait for entry to expire

        $this->assertEmpty($store->get());
    }

    #[Test]
    public function it_keeps_valid_entries(): void
    {
        $store = $this->createStore(time: 5);
        $record = $this->createLogRecord();

        $store->add($record, 'test-signature');
        $entries = $store->get();

        $this->assertCount(1, $entries);
    }

    #[Test]
    public function it_handles_multiple_entries(): void
    {
        $store = $this->createStore();
        $record1 = $this->createLogRecord('test1');
        $record2 = $this->createLogRecord('test2');

        $store->add($record1, 'signature1');
        $store->add($record2, 'signature2');

        $entries = $store->get();
        $this->assertCount(2, $entries);
    }

    abstract protected function createStore(string $prefix = 'test:', int $time = 60): mixed;
}
