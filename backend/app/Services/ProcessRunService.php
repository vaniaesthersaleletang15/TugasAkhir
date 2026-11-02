<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ProcessRunService
{
    public function start(string $processName, array $metadata = []): int
    {
        return DB::table('process_runs')->insertGetId([
            'process_name' => $processName,
            'status' => 'running',
            'started_at' => now(),
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function finish(int $id, string $message, array $metadata = []): void
    {
        DB::table('process_runs')->where('id', $id)->update([
            'status' => 'success',
            'finished_at' => now(),
            'message' => $message,
            'metadata' => $metadata === [] ? DB::raw('metadata') : json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function fail(int $id, string $error, ?string $message = null): void
    {
        DB::table('process_runs')->where('id', $id)->update([
            'status' => 'failed',
            'finished_at' => now(),
            'message' => $message,
            'error_message' => $error,
            'updated_at' => now(),
        ]);
    }
}
