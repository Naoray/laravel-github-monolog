<?php

namespace Naoray\LaravelGithubMonolog\DeduplicationStores;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Monolog\LogRecord;

class DatabaseDeduplicationStore extends AbstractDeduplicationStore
{
    private string $table;
    private string $connection;

    public function __construct(
        string $connection = 'default',
        string $table = 'github_monolog_deduplication',
        string $prefix = 'github-monolog:',
        int $time = 60
    ) {
        parent::__construct($prefix, $time);

        $this->connection = $connection === 'default' ? config('database.default') : $connection;
        $this->table = $table;

        $this->ensureTableExists();
    }

    public function get(): array
    {
        $this->cleanup();

        return DB::connection($this->connection)
            ->table($this->table)
            ->where('prefix', $this->prefix)
            ->get()
            ->map(fn($row) => $this->formatEntry($row->signature, $row->created_at))
            ->all();
    }

    public function add(LogRecord $record, string $signature): void
    {
        DB::connection($this->connection)
            ->table($this->table)
            ->insert([
                'prefix' => $this->prefix,
                'signature' => $signature,
                'created_at' => time(),
            ]);
    }

    private function cleanup(): void
    {
        DB::connection($this->connection)
            ->table($this->table)
            ->where('prefix', $this->prefix)
            ->where('created_at', '<', time() - $this->time)
            ->delete();
    }

    private function ensureTableExists(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->table)) {
            Schema::connection($this->connection)->create($this->table, function (Blueprint $table) {
                $table->id();
                $table->string('prefix')->index();
                $table->string('signature');
                $table->integer('created_at')->index();

                $table->index(['prefix', 'signature', 'created_at']);
            });
        }
    }
}
