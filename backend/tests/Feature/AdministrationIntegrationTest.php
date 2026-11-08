<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SystemMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministrationIntegrationTest extends TestCase
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
        return $user->createToken('integration-admin')->plainTextToken;
    }

    public function test_disabling_user_revokes_tokens_and_writes_audit(): void
    {
        $admin = $this->user('admin', 'status_admin');
        $target = $this->user('hsse', 'status_target');
        $targetToken = $this->token($target);

        $this->withToken($this->token($admin))
            ->patchJson("/api/v1/users/{$target->id}/status", ['status' => 'inactive'])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'inactive']);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => (int) str($targetToken)->before('|')->toString(),
        ]);
        $this->assertDatabaseHas('audit_log', [
            'action' => 'USER_STATUS',
            'user_name' => $admin->username,
        ]);

        $this->app['auth']->forgetGuards();
        $this->withToken($targetToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_admin_password_reset_changes_password_and_revokes_existing_tokens(): void
    {
        $admin = $this->user('admin', 'password_admin');
        $target = $this->user('pengurus', 'password_target');
        $oldToken = $this->token($target);

        $this->withToken($this->token($admin))
            ->postJson("/api/v1/users/{$target->id}/reset-password", [
                'new_password' => 'Changed123!',
            ])->assertOk();

        $this->assertTrue(Hash::check('Changed123!', $target->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => (int) str($oldToken)->before('|')->toString(),
        ]);
        $this->assertDatabaseHas('audit_log', ['action' => 'PASSWORD_RESET']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $target->email,
            'password' => 'Changed123!',
            'portal' => 'transportir',
        ])->assertOk();
    }

    public function test_registration_rejection_persists_reason_without_creating_user(): void
    {
        $admin = $this->user('admin', 'reject_registration_admin');
        $transporterId = $this->createTransporter($this->terminal(), 'PT Pendaftaran Ditolak');
        $payload = [
            'email' => 'rejected.transportir@example.test',
            'password' => 'Strong123!',
            'full_name' => 'Transportir Ditolak',
            'phone' => '081234',
            'reason' => 'Operasional',
            'requested_role' => 'pengurus',
            'portal' => 'transportir',
            'terminal_id' => $this->terminal(),
            'transporter_id' => $transporterId,
        ];
        $this->postJson('/api/v1/auth/register', $payload)->assertOk();
        $registrationId = (int) DB::table('user_registrations')->where('email', $payload['email'])->value('id');

        $this->withToken($this->token($admin))
            ->postJson("/api/v1/registrations/$registrationId/review", [
                'action' => 'reject',
                'reason' => 'Dokumen perusahaan belum lengkap.',
            ])->assertOk();

        $this->assertDatabaseHas('user_registrations', [
            'id' => $registrationId,
            'status' => 'rejected',
            'rejection_reason' => 'Dokumen perusahaan belum lengkap.',
            'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        $this->assertDatabaseHas('audit_log', ['action' => 'REGISTRATION_REVIEW']);
    }

    public function test_document_rejection_records_reviewer_and_does_not_change_draft_expiry(): void
    {
        $admin = $this->user('admin', 'reject_document_admin');
        $owner = $this->user('pengurus', 'reject_document_owner');
        $draftId = DB::table('formulir_checklist')->insertGetId([
            'terminal_id' => $this->terminal(),
            'jenis_kendaraan' => 'SPBU',
            'nomor_polisi' => 'DB 8181 XX',
            'tanggal_pemeriksaan' => today(),
            'status_approval' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $oldExpiry = today()->addMonth()->toDateString();
        DB::table('checklist_items')->insert([
            'formulir_id' => $draftId,
            'item_number' => 1,
            'item_name' => 'STNK',
            'tanggal_expire' => $oldExpiry,
        ]);
        $documentId = DB::table('dokumen_kendaraan')->insertGetId([
            'terminal_id' => $this->terminal(),
            'nomor_polisi' => 'DB 8181 XX',
            'jenis_dokumen' => 'STNK',
            'tanggal_berlaku' => today()->addYear(),
            'uploaded_by' => $owner->id,
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken($this->token($admin))
            ->postJson("/api/v1/documents/$documentId/review", [
                'action' => 'reject',
                'reason' => 'Dokumen tidak terbaca.',
            ])->assertOk()->assertJsonPath('data.updated_draft_items', 0);

        $this->assertDatabaseHas('dokumen_kendaraan', [
            'id' => $documentId,
            'status' => 'DITOLAK',
            'catatan_admin' => 'Dokumen tidak terbaca.',
            'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('checklist_items', [
            'formulir_id' => $draftId,
            'tanggal_expire' => $oldExpiry,
        ]);
    }

    public function test_system_settings_are_clamped_and_audited(): void
    {
        $admin = $this->user('admin', 'settings_admin');

        $this->withToken($this->token($admin))->putJson('/api/v1/settings', [
            'session_timeout_minutes' => 2,
            'audit_retention_days' => 9999,
            'backup_retention_days' => 1,
            'maintenance_mode' => false,
            'maintenance_message' => '  ',
        ])->assertOk();

        $expected = [
            'session_timeout_minutes' => '15',
            'audit_retention_days' => '3650',
            'backup_retention_days' => '7',
            'maintenance_mode' => '0',
            'maintenance_message' => 'Sistem sedang dalam pemeliharaan.',
        ];
        foreach ($expected as $key => $value) {
            $this->assertDatabaseHas('system_settings', [
                'setting_key' => $key,
                'setting_value' => $value,
            ]);
        }
        $this->assertDatabaseHas('audit_log', ['action' => 'SYSTEM_SETTINGS']);
    }

    public function test_smtp_configuration_encrypts_password_and_never_returns_it(): void
    {
        $admin = $this->user('admin', 'smtp_admin');
        $token = $this->token($admin);
        $this->mock(SystemMailer::class, function ($mock): void {
            $mock->shouldReceive('verifyConnection')->once();
        });

        $this->withToken($token)->putJson('/api/v1/notifications', [
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'mailer@example.test',
            'smtp_from_email' => 'prima@example.test',
            'smtp_from_name' => 'PRIMA',
            'smtp_password' => 'smtp-secret',
            'notif_days_threshold' => 30,
        ])->assertOk();

        $encrypted = DB::table('system_settings')->where('setting_key', 'smtp_password')->value('setting_value');
        $this->assertNotSame('smtp-secret', $encrypted);
        $this->assertSame('smtp-secret', decrypt($encrypted));
        $this->assertDatabaseHas('system_settings', ['setting_key' => 'smtp_last_error', 'setting_value' => '']);
        $this->assertNotEmpty(DB::table('system_settings')->where('setting_key', 'smtp_verified_at')->value('setting_value'));

        $this->app['auth']->forgetGuards();
        $response = $this->withToken($token)->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.smtp_ready', true);
        $this->assertArrayNotHasKey('smtp_password', $response->json('data.config'));
    }

    public function test_invalid_smtp_credentials_are_rejected_without_replacing_active_configuration(): void
    {
        $admin = $this->user('admin', 'smtp_invalid_admin');
        $token = $this->token($admin);
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'smtp_host'],
            ['setting_value' => 'smtp.previous.test', 'updated_at' => now()],
        );
        $this->mock(SystemMailer::class, function ($mock): void {
            $mock->shouldReceive('verifyConnection')
                ->once()
                ->andThrow(new \RuntimeException('535 BadCredentials'));
        });

        $this->withToken($token)->putJson('/api/v1/notifications', [
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'mailer@example.test',
            'smtp_from_email' => 'prima@example.test',
            'smtp_from_name' => 'PRIMA',
            'smtp_password' => 'invalid-secret',
            'notif_days_threshold' => 30,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Autentikasi SMTP Gmail gagal. Gunakan Google App Password 16 karakter, bukan password akun Gmail.');

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'smtp_host',
            'setting_value' => 'smtp.previous.test',
        ]);
        $this->assertDatabaseMissing('system_settings', ['setting_value' => 'invalid-secret']);
        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'smtp_verified_at',
            'setting_value' => '',
        ]);
    }

    public function test_cron_secret_regeneration_invalidates_previous_secret(): void
    {
        $admin = $this->user('admin', 'cron_admin');
        DB::table('system_settings')->insert([
            'setting_key' => 'cron_secret',
            'setting_value' => 'old-secret',
            'updated_at' => now(),
        ]);

        $this->withToken($this->token($admin))
            ->postJson('/api/v1/notifications/regenerate-cron')
            ->assertOk();

        $newSecret = DB::table('system_settings')->where('setting_key', 'cron_secret')->value('setting_value');
        $this->assertNotSame('old-secret', $newSecret);
        $this->assertSame(48, strlen($newSecret));
        $this->postJson('/api/v1/scheduler/run/old-secret')->assertForbidden();
    }

    public function test_admin_audits_and_alerts_are_scoped_to_own_terminal(): void
    {
        $bitung = $this->terminal();
        $kendari = $this->terminal('IT-KENDARI');
        $admin = $this->user('admin', 'scope_admin', $bitung);
        DB::table('audit_log')->insert([
            ['terminal_id' => $bitung, 'action' => 'OWN', 'description' => 'Own audit', 'created_at' => now()],
            ['terminal_id' => $kendari, 'action' => 'OTHER', 'description' => 'Other audit', 'created_at' => now()],
        ]);
        foreach ([[$bitung, 'DB 1111 AA'], [$kendari, 'DT 2222 BB']] as [$terminalId, $plate]) {
            DB::table('formulir_checklist')->insert([
                'terminal_id' => $terminalId,
                'jenis_kendaraan' => 'SPBU',
                'nomor_polisi' => $plate,
                'tanggal_pemeriksaan' => today(),
                'ekim_valid_until' => today()->addDays(10),
                'status_approval' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $token = $this->token($admin);

        $this->withToken($token)->getJson('/api/v1/audits')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'OWN');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/alerts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nomor_polisi', 'DB 1111 AA');
    }
}
