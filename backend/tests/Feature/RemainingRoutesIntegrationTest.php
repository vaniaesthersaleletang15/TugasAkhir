<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RemainingRoutesIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function terminal(string $code = 'IT-BITUNG'): int
    {
        return (int) DB::table('integrated_terminals')->where('code', $code)->value('id');
    }

    private function user(string $role, string $username, ?int $terminalId = null): User
    {
        return User::create([
            'username' => $username,
            'full_name' => ucfirst($username),
            'email' => "$username@example.test",
            'password' => 'Secret123!',
            'role' => $role,
            'terminal_id' => $terminalId ?? $this->terminal(),
            'status' => 'active',
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('remaining-routes')->plainTextToken;
    }

    public function test_document_list_is_scoped_by_owner_terminal_and_role(): void
    {
        $bitung = $this->terminal();
        $kendari = $this->terminal('IT-KENDARI');
        $admin = $this->user('admin', 'document_list_admin', $bitung);
        $owner = $this->user('pengurus', 'document_list_owner', $bitung);
        $otherOwner = $this->user('pengurus', 'document_list_other', $kendari);
        $ownerTransporter = $this->createTransporter($bitung, 'PT Dokumen Bitung');
        $otherTransporter = $this->createTransporter($kendari, 'PT Dokumen Kendari');
        $this->linkTransporter($owner, $ownerTransporter);
        $this->linkTransporter($otherOwner, $otherTransporter);
        foreach ([[$bitung, $owner->id, $ownerTransporter, 'DB 5151 RA'], [$kendari, $otherOwner->id, $otherTransporter, 'DT 5252 RB']] as [$terminalId, $userId, $transporterId, $plate]) {
            $vehicleId = DB::table('kendaraan')->insertGetId(['terminal_id' => $terminalId, 'transporter_id' => $transporterId, 'jenis' => 'SPBU', 'nomor_polisi' => $plate, 'merk_mobil' => 'Hino', 'status' => 'AKTIF', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('dokumen_kendaraan')->insert([
                'terminal_id' => $terminalId,
                'vehicle_id' => $vehicleId,
                'transporter_id' => $transporterId,
                'nomor_polisi' => $plate,
                'jenis_dokumen' => 'STNK',
                'uploaded_by' => $userId,
                'status' => 'PENDING',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->withToken($this->token($owner))->getJson('/api/v1/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nomor_polisi', 'DB 5151 RA');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($admin))->getJson('/api/v1/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nomor_polisi', 'DB 5151 RA');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->user('hsse', 'document_list_hsse', $bitung)))
            ->getJson('/api/v1/documents')
            ->assertForbidden();
    }

    public function test_settings_endpoint_returns_database_storage_backup_and_policy_integration(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin', 'settings_read_admin');
        Storage::disk('local')->put('backups/prima-db-test.json.gz', 'backup');
        Storage::disk('local')->put('backups/prima-db-test.json.gz.sha256', hash('sha256', 'backup'));
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'session_timeout_minutes'],
            ['setting_value' => '120', 'updated_at' => now()]
        );

        $response = $this->withToken($this->token($admin))->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.health.database.ok', true)
            ->assertJsonPath('data.policies.session_timeout_minutes', 120)
            ->assertJsonCount(1, 'data.backup.items');

        $this->assertNotEmpty($response->json('data.tables'));
        $this->assertSame('prima-db-test.json.gz', $response->json('data.backup.items.0.filename'));
    }

    public function test_retention_endpoint_connects_route_service_database_storage_and_audit(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin', 'retention_endpoint_admin');
        DB::table('system_settings')->insert([
            ['setting_key' => 'audit_retention_days', 'setting_value' => '90', 'updated_at' => now()],
            ['setting_key' => 'backup_retention_days', 'setting_value' => '7', 'updated_at' => now()],
        ]);
        DB::table('audit_log')->insert([
            ['terminal_id' => $admin->terminal_id, 'action' => 'OLD_ROUTE', 'created_at' => now()->subDays(91)],
            ['terminal_id' => $admin->terminal_id, 'action' => 'CURRENT_ROUTE', 'created_at' => now()],
        ]);
        Storage::disk('local')->put('backups/old-route.dump', 'old');
        Storage::disk('local')->put('backups/old-route.dump.sha256', 'checksum');
        touch(Storage::disk('local')->path('backups/old-route.dump'), now()->subDays(8)->timestamp);

        $this->withToken($this->token($admin))
            ->postJson('/api/v1/settings/audit-retention')
            ->assertOk()
            ->assertJsonPath('data.audit_deleted', 1)
            ->assertJsonPath('data.backups_deleted', 1);

        $this->assertDatabaseMissing('audit_log', ['action' => 'OLD_ROUTE']);
        $this->assertDatabaseHas('audit_log', ['action' => 'CURRENT_ROUTE']);
        $this->assertDatabaseHas('audit_log', ['action' => 'RETENTION']);
        Storage::disk('local')->assertMissing('backups/old-route.dump');
        Storage::disk('local')->assertMissing('backups/old-route.dump.sha256');
    }

    public function test_backup_endpoint_persists_service_result_settings_and_audit(): void
    {
        $admin = $this->user('admin', 'backup_endpoint_admin');
        $this->mock(DatabaseBackupService::class, function ($mock): void {
            $mock->shouldReceive('create')->once()->andReturn([
                'filename' => 'prima-db-integration.json.gz',
                'size_bytes' => 2048,
                'checksum' => str_repeat('a', 64),
                'format' => 'prima-portable-json',
            ]);
        });

        $this->withToken($this->token($admin))->postJson('/api/v1/settings/backup')
            ->assertOk()
            ->assertJsonPath('data.filename', 'prima-db-integration.json.gz')
            ->assertJsonPath('data.format', 'prima-portable-json')
            ->assertJsonPath('data.checksum', str_repeat('a', 64));

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'last_backup_status',
            'setting_value' => 'success',
        ]);
        $this->assertDatabaseHas('audit_log', [
            'action' => 'BACKUP',
            'user_name' => $admin->username,
        ]);
    }
}
