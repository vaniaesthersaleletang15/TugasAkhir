<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    private const EPHEMERAL_TABLES = [
        'personal_access_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'failed_jobs',
        'job_batches',
        'password_reset_tokens',
    ];

    public function __construct(private readonly RetentionService $retention) {}

    public function create(): array
    {
        $directory = storage_path('app/private/backups');
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }

        $filename = 'prima-db-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.dump';
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        if (! $this->createPostgresBackup($path)) {
            @unlink($path);
            $filename = str_replace('.dump', '.json.gz', $filename);
            $path = $directory.DIRECTORY_SEPARATOR.$filename;
            $this->createPortableBackup($path);
        }

        $checksum = hash_file('sha256', $path);
        if ($checksum === false || file_put_contents($path.'.sha256', $checksum) === false) {
            throw new RuntimeException('Checksum backup tidak dapat dibuat.');
        }

        $this->retention->pruneBackups();

        return [
            'filename' => $filename,
            'size_bytes' => filesize($path),
            'checksum' => $checksum,
            'format' => str_ends_with($filename, '.dump') ? 'postgresql-custom' : 'prima-portable-json',
        ];
    }

    private function createPostgresBackup(string $path): bool
    {
        $config = config('database.connections.pgsql');
        $process = new Process([
            config('prima.pg_dump_binary', 'pg_dump'),
            '--format=custom',
            '--no-owner',
            '--no-privileges',
            ...array_map(fn (string $table) => "--exclude-table-data=public.$table", self::EPHEMERAL_TABLES),
            '--file='.$path,
            '--host='.$config['host'],
            '--port='.(string) $config['port'],
            '--username='.$config['username'],
            $config['database'],
        ], null, ['PGPASSWORD' => $config['password']]);

        $process->setTimeout(300);
        $process->run();
        if ($process->isSuccessful()) {
            return true;
        }

        logger()->warning('pg_dump gagal; menggunakan backup portabel Laravel', [
            'error' => $process->getErrorOutput(),
            'output' => $process->getOutput(),
            'exit_code' => $process->getExitCode(),
        ]);

        return false;
    }

    private function createPortableBackup(string $path): void
    {
        $tables = collect(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"))
            ->pluck('tablename');
        $payload = [
            'format' => 'prima-portable-backup-v1',
            'created_at' => now()->toIso8601String(),
            'database' => config('database.connections.pgsql.database'),
            'tables' => [],
        ];

        foreach ($tables as $table) {
            $payload['tables'][$table] = in_array($table, self::EPHEMERAL_TABLES, true)
                ? []
                : DB::table($table)
                    ->orderBy(Schema::hasColumn($table, 'id') ? 'id' : Schema::getColumnListing($table)[0])
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->all();
        }

        $compressed = gzencode(json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ), 9);

        if ($compressed === false || file_put_contents($path, $compressed, LOCK_EX) === false) {
            throw new RuntimeException('Gagal membuat backup portabel.');
        }
    }
}
