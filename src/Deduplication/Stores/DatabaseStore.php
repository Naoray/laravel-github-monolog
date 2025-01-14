<?php

namespace Naoray\LaravelGithubMonolog\Deduplication\Stores;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Monolog\LogRecord;

class DatabaseStore extends AbstractStore
{
    private string $table;

    private string $connection;

    public function __construct(
        string $connection = 'default',
        string $table = 'github_monolog_deduplication',
        int $time = 60
    ) {
        parent::__construct($time);

        $this->connection = $connection === 'default' ? config('database.default') : $connection;
        $this->table = $table;

        $this->ensureTableExists();
    }

    public function get(): array
    {
        return DB::connection($this->connection)
            ->table($this->table)
            ->where('created_at', '>=', $this->getTimestampValidity())
            ->get()
            ->map(fn ($row) => $this->buildEntry($row->signature, $row->created_at))
            ->all();
    }

    public function add(LogRecord $record, string $signature): void
    {
        DB::connection($this->connection)
            ->table($this->table)
            ->insert([
                'signature' => $signature,
                'created_at' => $this->getTimestamp(),
            ]);
    }

    public function cleanup(): void
    {
        DB::connection($this->connection)
            ->table($this->table)
            ->where('created_at', '<', $this->getTimestampValidity())
            ->delete();
    }

    public function ensureTableExists(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->table)) {
            Schema::connection($this->connection)->create($this->table, function (Blueprint $table) {
                $table->id();
                $table->string('signature');
                $table->integer('created_at')->index();

                $table->index(['signature', 'created_at']);
            });
        }
    }
}
