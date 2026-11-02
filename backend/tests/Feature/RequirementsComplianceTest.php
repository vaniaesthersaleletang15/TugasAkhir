<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequirementsComplianceTest extends TestCase
{
    use RefreshDatabase;

    private function terminal(string $code = 'IT-BITUNG'): int
    {
        return (int) DB::table('integrated_terminals')->where('code', $code)->value('id');
    }

    private function user(string $role, string $key, ?int $terminalId = null): User
    {
        return User::create([
            'username' => $key,
            'full_name' => strtoupper($key),
            'email' => "$key@example.test",
            'password' => 'Secret123!',
            'role' => $role,
            'terminal_id' => $terminalId ?? $this->terminal(),
            'region_code' => 'SULAWESI',
            'status' => 'active',
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('requirements-test')->plainTextToken;
    }

    public function test_profile_and_password_require_current_password_and_are_audited(): void
    {
        $user = $this->user('hsse', 'profile_hsse');

        $this->withToken($this->token($user))->putJson('/api/v1/auth/profile', [
            'full_name' => 'Nama Baru', 'email' => 'baru.profile@example.test', 'phone' => '081234', 'current_password' => 'salah',
        ])->assertUnprocessable();

        $this->withToken($this->token($user))->putJson('/api/v1/auth/profile', [
            'full_name' => 'Nama Baru', 'email' => 'baru.profile@example.test', 'phone' => '081234', 'current_password' => 'Secret123!',
        ])->assertOk()->assertJsonPath('data.full_name', 'Nama Baru');

        $this->withToken($this->token($user))->putJson('/api/v1/auth/password', [
            'current_password' => 'Secret123!', 'password' => 'Changed123!', 'password_confirmation' => 'Changed123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('Changed123!', $user->fresh()->password));
        $this->assertDatabaseHas('audit_log', ['action' => 'PROFILE_UPDATE', 'terminal_id' => $user->terminal_id]);
        $this->assertDatabaseHas('audit_log', ['action' => 'PASSWORD_CHANGE', 'terminal_id' => $user->terminal_id]);
    }

    public function test_master_transporter_and_pengurus_relationship_are_terminal_scoped(): void
    {
        $admin = $this->user('admin', 'spv_transporters');
        $otherAdmin = $this->user('admin', 'spv_other', $this->terminal('IT-KENDARI'));

        $response = $this->withToken($this->token($admin))->postJson('/api/v1/master-transporters', [
            'code' => 'TR-BTG-01', 'name' => 'PT Angkutan Bitung', 'email' => 'angkutan@example.test', 'phone' => '0812', 'address' => 'Bitung',
        ])->assertOk();
        $transporterId = (int) $response->json('data.id');

        $this->withToken($this->token($admin))->postJson('/api/v1/users', [
            'full_name' => 'Pengurus Angkutan', 'email' => 'pengurus.angkutan@example.test', 'phone' => '0813',
            'role' => 'pengurus', 'password' => 'Secret123!', 'transporter_ids' => [$transporterId],
        ])->assertCreated();

        $this->assertDatabaseHas('transporter_user', ['transporter_id' => $transporterId]);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($otherAdmin))->putJson("/api/v1/master-transporters/$transporterId", [
            'code' => 'HACK', 'name' => 'Tidak Boleh',
        ])->assertNotFound();
    }

    public function test_pengurus_registration_vehicle_approval_and_history_are_traceable(): void
    {
        $admin = $this->user('admin', 'spv_vehicle');
        $pengurus = $this->user('pengurus', 'pengurus_vehicle');
        $transporterId = $this->createTransporter($this->terminal(), 'PT Kendaraan Patuh');
        $this->linkTransporter($pengurus, $transporterId, $admin->id);

        $this->withToken($this->token($pengurus))->postJson('/api/v1/my-vehicles', [
            'transporter_id' => $transporterId, 'jenis' => 'SPBU', 'nomor_polisi' => 'db 9988 xy',
            'nomor_urut' => 'MT-099', 'merk_mobil' => 'Hino 500', 'tahun_kendaraan' => 2024,
            'produk' => 'Pertalite', 'kapasitas' => '16 KL',
        ])->assertOk();
        $vehicle = DB::table('kendaraan')->where('nomor_polisi', 'DB 9988 XY')->first();
        $this->assertSame('PENDING', $vehicle->approval_status);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($admin))->postJson('/api/v1/vehicles/manage', [
            'id' => $vehicle->id, 'action' => 'approve', 'review_note' => 'Identitas dan kendaraan sesuai.',
        ])->assertOk();
        $this->withToken($this->token($admin))->getJson("/api/v1/vehicles/{$vehicle->id}/history")
            ->assertOk()->assertJsonCount(2, 'data');
        $this->assertDatabaseHas('vehicle_histories', ['vehicle_id' => $vehicle->id, 'action' => 'APPROVE']);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($pengurus))->putJson("/api/v1/my-vehicles/{$vehicle->id}", [
            'jenis' => 'SPBU', 'nomor_polisi' => 'DB 9988 XY', 'nomor_urut' => 'MT-100',
            'merk_mobil' => 'Hino 500', 'tahun_kendaraan' => 2024, 'produk' => 'Pertamax', 'kapasitas' => '16 KL',
        ])->assertOk();
        $this->assertDatabaseHas('kendaraan', ['id' => $vehicle->id, 'nomor_urut' => 'MT-100', 'approval_status' => 'PENDING']);

        $other = $this->user('pengurus', 'pengurus_lain');
        $otherTransporter = $this->createTransporter($this->terminal(), 'PT Transportir Lain');
        $this->linkTransporter($other, $otherTransporter, $admin->id);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($other))->putJson("/api/v1/my-vehicles/{$vehicle->id}", [
            'jenis' => 'SPBU', 'nomor_polisi' => 'DB 9988 XY', 'nomor_urut' => 'ILEGAL',
            'merk_mobil' => 'Hino', 'tahun_kendaraan' => 2024, 'produk' => 'Solar', 'kapasitas' => '16 KL',
        ])->assertForbidden();
    }

    public function test_regional_document_completeness_is_read_only_and_scoped(): void
    {
        $regional = $this->user('regional_manager', 'regional_documents', null);
        $regional->terminal_id = null;
        $regional->save();
        $vehicleId = DB::table('kendaraan')->insertGetId([
            'terminal_id' => $this->terminal(), 'jenis' => 'SPBU', 'nomor_polisi' => 'DB 7777 AA',
            'merk_mobil' => 'Hino', 'nama_transport' => 'PT Regional', 'email_transportir' => 'regional@example.test',
            'status' => 'AKTIF', 'approval_status' => 'APPROVED',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withToken($this->token($regional))->getJson('/api/v1/regional/documents')
            ->assertOk()->assertJsonPath('data.0.vehicle_id', $vehicleId)->assertJsonPath('data.0.is_complete', false);
        $this->withToken($this->token($regional))->postJson('/api/v1/vehicles/manage', ['id' => $vehicleId, 'action' => 'deactivate'])
            ->assertForbidden();
    }

    public function test_restore_requires_manager_approval_and_admin_reauthentication(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin', 'restore_spv');
        $manager = $this->user('manager_hsse', 'restore_manager');
        $filename = 'prima-backup-20261101-120000.dump';
        $contents = '-- valid test backup';
        Storage::disk('local')->put("backups/$filename", $contents);
        Storage::disk('local')->put("backups/$filename.sha256", hash('sha256', $contents));

        $response = $this->withToken($this->token($admin))->postJson('/api/v1/settings/restore-requests', [
            'filename' => $filename, 'request_notes' => 'Pemulihan data terkontrol untuk pengujian.',
        ])->assertCreated();
        $id = (int) $response->json('data.id');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($admin))->postJson("/api/v1/settings/restore-requests/$id/execute", [
            'current_password' => 'Secret123!',
        ])->assertStatus(409);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($manager))->postJson("/api/v1/settings/restore-requests/$id/decision", [
            'decision' => 'approve', 'approval_notes' => 'Disetujui setelah validasi kebutuhan.',
        ])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($admin))->postJson("/api/v1/settings/restore-requests/$id/execute", [
            'current_password' => 'password-salah',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('restore_requests', ['id' => $id, 'status' => 'approved', 'approved_by' => $manager->id]);
        $this->assertDatabaseHas('audit_log', ['action' => 'RESTORE_REQUEST']);
        $this->assertDatabaseHas('audit_log', ['action' => 'RESTORE_DECISION']);
    }

    public function test_common_api_responses_complete_within_three_seconds(): void
    {
        $user = $this->user('hsse', 'performance_hsse');
        $token = $this->token($user);
        foreach (['/api/v1/auth/me', '/api/v1/dashboard', '/api/v1/vehicles', '/api/v1/checklists'] as $uri) {
            $started = hrtime(true);
            $this->withToken($token)->getJson($uri)->assertOk();
            $seconds = (hrtime(true) - $started) / 1_000_000_000;
            $this->assertLessThan(3, $seconds, "$uri melebihi batas respons tiga detik");
        }
    }
}
