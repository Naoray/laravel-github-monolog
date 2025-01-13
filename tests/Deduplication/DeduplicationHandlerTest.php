<?php

namespace Naoray\LaravelGithubMonolog\Tests\Handlers;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\DeduplicationStores\FileDeduplicationStore;
use Naoray\LaravelGithubMonolog\Handlers\SignatureDeduplicationHandler;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SignatureDeduplicationHandlerTest extends TestCase
{
    private TestHandler $testHandler;

    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testHandler = new TestHandler;
        $this->tempFile = sys_get_temp_dir().'/dedup-test-'.uniqid().'.log';
    }

    protected function tearDown(): void
    {
        @unlink($this->tempFile);
        parent::tearDown();
    }

    #[Test]
    public function deduplication_respects_time_window(): void
    {
        $store = new FileDeduplicationStore($this->tempFile);
        $handler = new SignatureDeduplicationHandler($this->testHandler, $store, time: 1);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Error,
            message: 'Test',
            context: [],
            extra: [],
        );

        $handler->handle($record);
        $this->assertCount(1, $this->testHandler->getRecords());

        sleep(2);
        $handler->handle($record);
        $this->assertCount(2, $this->testHandler->getRecords());
    }

    #[Test]
    public function deduplicates_records_with_same_signature(): void
    {
        $store = new FileDeduplicationStore($this->tempFile);
        $handler = new SignatureDeduplicationHandler($this->testHandler, $store);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Error,
            message: 'Test',
            context: [],
            extra: [],
        );

        $handler->handle($record);
        $handler->handle($record);

        $this->assertCount(1, $this->testHandler->getRecords());
    }
}
