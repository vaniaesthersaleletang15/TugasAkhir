<?php

namespace App\Console\Commands;

use App\Domain\System\BackupFilePolicy;
use App\Services\ProcessRunService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class CreateDatabaseBackup extends Command
{
    protected $signature = 'prima:database-backup {filename}';

    protected $description = 'Buat backup PostgreSQL berformat custom ke private storage';

    public function __construct(
        private readonly BackupFilePolicy $backupFilePolicy,
        private readonly ProcessRunService $processRuns,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $filename = (string) $this->argument('filename');
        $runId = $this->processRuns->start('database_backup', ['trigger' => 'scheduled', 'filename' => $filename]);
        if (! $this->backupFilePolicy->validCreateFilename($filename)) {
            $this->processRuns->fail($runId, 'Nama file backup tidak valid.');
            $this->error('Nama file backup tidak valid.');

            return self::FAILURE;
        }

        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory, 0750);
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        $database = config('database.connections.pgsql');
        $passFile = $directory.DIRECTORY_SEPARATOR.'.pgpass-'.bin2hex(random_bytes(8));
        $escape = fn ($value) => str_replace(['\\', ':'], ['\\\\', '\\:'], (string) $value);
        file_put_contents($passFile, implode(':', array_map($escape, [$database['host'], $database['port'], $database['database'], $database['username'], $database['password']])).PHP_EOL);
        chmod($passFile, 0600);
        $connectionEscape = fn ($value) => str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value);
        $connection = "host='".$connectionEscape($database['host'])."' port='".$connectionEscape($database['port'])."' dbname='".$connectionEscape($database['database'])."' user='".$connectionEscape($database['username'])."' passfile='".$connectionEscape(str_replace('\\', '/', $passFile))."'";
        $process = new Process([
            config('prima.pg_dump_binary', 'pg_dump'),
            '--format=custom',
            '--no-owner',
            '--no-privileges',
            '--exclude-table-data=public.personal_access_tokens',
            '--exclude-table-data=public.sessions',
            '--exclude-table-data=public.cache',
            '--exclude-table-data=public.cache_locks',
            '--exclude-table-data=public.jobs',
            '--exclude-table-data=public.failed_jobs',
            '--exclude-table-data=public.job_batches',
            '--exclude-table-data=public.password_reset_tokens',
            '--no-password',
            '--file='.$path,
            '--dbname='.$connection,
        ]);
        $process->setTimeout(300);
        try {
            $process->run();
        } finally {
            @unlink($passFile);
        }

        if (! $process->isSuccessful()) {
            @unlink($path);
            $error = trim($process->getErrorOutput()) ?: 'pg_dump gagal tanpa pesan tambahan.';
            $this->recordResult('failed', $error);
            $this->processRuns->fail($runId, $error);
            $this->error($error);

            return self::FAILURE;
        }

        $checksum = hash_file('sha256', $path);
        file_put_contents($path.'.sha256', $checksum, LOCK_EX);
        $this->recordResult('success');
        $this->processRuns->finish($runId, "Backup {$filename} berhasil", ['filename' => $filename, 'checksum' => $checksum, 'size_bytes' => filesize($path)]);
        foreach (DB::table('integrated_terminals')->where('is_active', true)->pluck('id') as $terminalId) {
            DB::table('audit_log')->insert(['terminal_id' => $terminalId, 'action' => 'BACKUP_SCHEDULED', 'user_name' => 'SYSTEM', 'description' => "Backup {$filename} dibuat dengan checksum {$checksum}", 'created_at' => now()]);
        }

        $this->info($path);

        return self::SUCCESS;
    }

    private function recordResult(string $status, ?string $error = null): void
    {
        DB::table('system_settings')->upsert([
            ['setting_key' => 'last_backup_at', 'setting_value' => now()->toDateTimeString(), 'updated_at' => now()],
            ['setting_key' => 'last_backup_status', 'setting_value' => $status, 'updated_at' => now()],
            ['setting_key' => 'last_backup_error', 'setting_value' => $error ?? '', 'updated_at' => now()],
        ], ['setting_key'], ['setting_value', 'updated_at']);
    }
}
