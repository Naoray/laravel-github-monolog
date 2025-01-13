<?php

namespace Naoray\LaravelGithubMonolog\Tests\DeduplicationStores;

use Illuminate\Support\Facades\File;
use Naoray\LaravelGithubMonolog\DeduplicationStores\FileDeduplicationStore;
use PHPUnit\Framework\Attributes\Test;

class FileDeduplicationStoreTest extends AbstractDeduplicationStoreTest
{
    private string $testPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testPath = storage_path('logs/test-dedup.log');
        File::delete($this->testPath);
    }

    protected function tearDown(): void
    {
        File::delete($this->testPath);
        parent::tearDown();
    }

    protected function createStore(string $prefix = 'test:', int $time = 60): FileDeduplicationStore
    {
        return new FileDeduplicationStore(
            path: $this->testPath,
            prefix: $prefix,
            time: $time
        );
    }

    #[Test]
    public function it_creates_directory_if_not_exists(): void
    {
        $path = storage_path('logs/nested/test-dedup.log');
        File::deleteDirectory(dirname($path));

        new FileDeduplicationStore($path);

        $this->assertTrue(File::exists(dirname($path)));
        File::deleteDirectory(dirname($path));
    }

    #[Test]
    public function it_handles_concurrent_access(): void
    {
        $store1 = $this->createStore();
        $store2 = $this->createStore();
        $record = $this->createLogRecord();

        // Simulate concurrent access
        $store1->add($record, 'signature1');
        $store2->add($record, 'signature2');

        $entries = $store1->get();
        $this->assertCount(2, $entries);
    }

    #[Test]
    public function it_handles_corrupted_file(): void
    {
        // Create file with invalid content
        File::put($this->testPath, 'invalid content');

        $store = $this->createStore();
        $record = $this->createLogRecord();

        // Should handle invalid content gracefully
        $store->add($record, 'test-signature');
        $entries = $store->get();

        $this->assertNotEmpty($entries);
        $this->assertStringEndsWith('test-signature', $entries[array_key_first($entries)]);
    }

    #[Test]
    public function it_handles_file_permissions(): void
    {
        $store = $this->createStore();
        $record = $this->createLogRecord();

        $store->add($record, 'test-signature');

        // Verify file permissions
        $this->assertEquals('0644', substr(sprintf('%o', fileperms($this->testPath)), -4));
    }

    #[Test]
    public function it_maintains_file_integrity_after_cleanup(): void
    {
        $store = $this->createStore(time: 1);
        $record = $this->createLogRecord();

        // Add some entries
        $store->add($record, 'signature1');
        sleep(2); // Let first entry expire
        $store->add($record, 'signature2');

        // Get entries (triggers cleanup)
        $entries = $store->get();

        // Verify file content
        $content = File::get($this->testPath);
        $this->assertCount(1, explode(PHP_EOL, trim($content)));
        $this->assertStringEndsWith('signature2', $content);
    }
}
