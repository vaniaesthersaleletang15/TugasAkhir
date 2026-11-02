<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAndLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $username, string $status = 'active'): User
    {
        return User::create([
            'username' => $username,
            'full_name' => ucfirst($username),
            'email' => "$username@example.test",
            'password' => 'Secret123!',
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('integration-test')->plainTextToken;
    }

    public function test_five_wrong_passwords_lock_account_and_successful_login_after_expiry_resets_counter(): void
    {
        $user = $this->user('hsse', 'lockout_user');
        $payload = [
            'email' => $user->email,
            'password' => 'Wrong123!',
            'portal' => 'internal',
        ];

        foreach (range(1, 5) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => "127.0.0.$attempt"])
                ->postJson('/api/v1/auth/login', $payload)
                ->assertUnauthorized();
        }

        $user->refresh();
        $this->assertSame(5, $user->login_attempts);
        $this->assertTrue($user->locked_until->isFuture());

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.1.1'])
            ->postJson('/api/v1/auth/login', array_merge($payload, ['password' => 'Secret123!']))
            ->assertStatus(423)
            ->assertJsonPath('message', 'Akun terkunci sementara.');

        $user->forceFill(['locked_until' => now()->subSecond()])->save();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.1.2'])
            ->postJson('/api/v1/auth/login', array_merge($payload, ['password' => 'Secret123!']))
            ->assertOk();

        $user->refresh();
        $this->assertSame(0, $user->login_attempts);
        $this->assertNull($user->locked_until);
    }

    public function test_inactive_account_is_rejected_by_login_and_authenticated_middleware(): void
    {
        $user = $this->user('hsse', 'inactive_user', 'inactive');
        $token = $this->token($user);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
            'portal' => 'internal',
        ])->assertForbidden()->assertJsonPath('message', 'Akun belum aktif.');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Akun tidak aktif.');
    }

    public function test_maintenance_blocks_non_admin_but_admin_can_continue(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'maintenance_mode'],
            ['setting_value' => '1', 'updated_at' => now()]
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'maintenance_message'],
            ['setting_value' => 'Pemeliharaan integrasi.', 'updated_at' => now()]
        );
        $user = $this->user('hsse', 'maintenance_hsse');
        $admin = $this->user('admin', 'maintenance_admin');

        $this->withToken($this->token($user))->getJson('/api/v1/auth/me')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Pemeliharaan integrasi.');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($admin))->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
            'portal' => 'internal',
        ])->assertStatus(503)->assertJsonPath('message', 'Pemeliharaan integrasi.');
    }

    public function test_logout_revokes_only_current_access_token(): void
    {
        $user = $this->user('hsse', 'logout_user');
        $firstToken = $this->token($user);
        $secondToken = $this->token($user);

        $this->withToken($firstToken)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->app['auth']->forgetGuards();
        $this->withToken($firstToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($secondToken)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_expired_password_reset_token_cannot_change_password(): void
    {
        $user = $this->user('pengurus', 'expired_reset');
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make('expired-token'),
            'created_at' => now()->subMinutes(16),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'expired-token',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Tautan reset password tidak valid atau sudah kedaluwarsa.');

        $this->assertTrue(Hash::check('Secret123!', $user->fresh()->password));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_registration_approval_creates_active_transportir_that_can_login(): void
    {
        $terminalId = (int) DB::table('integrated_terminals')->where('code', 'IT-BITUNG')->value('id');
        $admin = $this->user('admin', 'registration_admin');
        $transporterId = $this->createTransporter($terminalId, 'PT Registrasi Disetujui');
        $payload = [
            'email' => 'approved.transportir@example.test',
            'password' => 'Strong123!',
            'full_name' => 'Transportir Disetujui',
            'phone' => '08123456789',
            'reason' => 'Operasional kendaraan',
            'requested_role' => 'pengurus',
            'portal' => 'transportir',
            'terminal_id' => $terminalId,
            'transporter_id' => $transporterId,
        ];

        $this->postJson('/api/v1/auth/register', $payload)->assertOk();
        $registration = DB::table('user_registrations')->where('email', $payload['email'])->first();
        $this->assertTrue(Hash::check('Strong123!', $registration->password));

        $this->withToken($this->token($admin))
            ->postJson("/api/v1/registrations/{$registration->id}/review", ['action' => 'approve', 'reason' => 'Identitas transportir telah diverifikasi.'])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => $payload['email'],
            'role' => 'pengurus',
            'status' => 'active',
            'terminal_id' => $terminalId,
        ]);
        $this->assertDatabaseHas('user_registrations', ['id' => $registration->id, 'status' => 'approved']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $payload['email'],
            'password' => 'Strong123!',
            'portal' => 'transportir',
        ])->assertOk()->assertJsonPath('data.user.role', 'pengurus');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($admin))
            ->postJson("/api/v1/registrations/{$registration->id}/review", ['action' => 'approve', 'reason' => 'Percobaan persetujuan ulang.'])
            ->assertStatus(409);
    }

    public function test_private_document_lifecycle_enforces_owner_and_removes_file_with_record(): void
    {
        Storage::fake('private');
        $owner = $this->user('pengurus', 'document_owner_lifecycle');
        $other = $this->user('pengurus', 'document_other_lifecycle');
        $transporterId = $this->createTransporter($owner->terminal_id, 'PT Dokumen Lifecycle');
        $this->linkTransporter($owner, $transporterId);
        DB::table('kendaraan')->insert([
            'terminal_id' => $owner->terminal_id,
            'transporter_id' => $transporterId,
            'jenis' => 'SPBU',
            'nomor_polisi' => 'DB 9090 ZZ',
            'merk_mobil' => 'Hino',
            'status' => 'AKTIF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('pengurus_kendaraan')->insert([
            'user_id' => $owner->id,
            'nomor_polisi' => 'DB 9090 ZZ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken($this->token($owner))->post('/api/v1/documents', [
            'jenis_dokumen' => 'STNK',
            'nomor_polisi' => 'DB 9090 ZZ',
            'tanggal_berlaku' => today()->addYear()->toDateString(),
            'file' => UploadedFile::fake()->create('stnk-lifecycle.pdf', 12, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        $document = DB::table('dokumen_kendaraan')->where('uploaded_by', $owner->id)->first();
        Storage::disk('private')->assertExists($document->file_path);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($other))
            ->get("/api/v1/documents/{$document->id}/download")
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($owner))
            ->get("/api/v1/documents/{$document->id}/download")
            ->assertOk()
            ->assertDownload('stnk-lifecycle.pdf');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($owner))
            ->deleteJson("/api/v1/documents/{$document->id}")
            ->assertOk();

        $this->assertDatabaseMissing('dokumen_kendaraan', ['id' => $document->id]);
        Storage::disk('private')->assertMissing($document->file_path);
    }
}
