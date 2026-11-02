<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VehicleAndRegionalIntegrationTest extends TestCase
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
            'terminal_id' => $terminalId,
            'region_code' => 'SULAWESI',
            'status' => 'active',
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('vehicle-regional-integration')->plainTextToken;
    }

    public function test_legacy_user_vehicle_registration_routes_are_removed(): void
    {
        $hsse = $this->user('hsse', 'legacy_vehicle_route_hsse', $this->terminal());
        $token = $this->token($hsse);

        $this->withToken($token)->getJson('/api/v1/user-vehicles')->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/user-vehicles', [])->assertNotFound();
    }

    public function test_transportir_can_claim_existing_vehicle_only_in_own_terminal_idempotently(): void
    {
        $bitung = $this->terminal();
        $owner = $this->user('pengurus', 'claim_owner', $bitung);
        $transporterId = $this->createTransporter($bitung, 'PT Claim');
        $this->linkTransporter($owner, $transporterId);
        DB::table('kendaraan')->insert([
            'terminal_id' => $bitung,
            'transporter_id' => $transporterId,
            'jenis' => 'SPBU',
            'nomor_polisi' => 'DB 6161 VB',
            'merk_mobil' => 'Hino',
            'status' => 'AKTIF',
            'approval_status' => 'APPROVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('kendaraan')->insert([
            'terminal_id' => $this->terminal('IT-KENDARI'),
            'jenis' => 'SPBU',
            'nomor_polisi' => 'DT 6262 VC',
            'merk_mobil' => 'Hino',
            'status' => 'AKTIF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $token = $this->token($owner);

        $this->withToken($token)->postJson('/api/v1/my-vehicles', [
            'nomor_polisi' => ' db 6161 vb ',
            'transporter_id' => $transporterId,
        ])->assertOk()->assertJsonPath('message', 'Kendaraan berhasil didaftarkan.');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/my-vehicles', [
            'nomor_polisi' => 'DB 6161 VB',
            'transporter_id' => $transporterId,
        ])->assertOk()->assertJsonPath('message', 'Kendaraan sudah terdaftar.');
        $this->assertDatabaseCount('pengurus_kendaraan', 1);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/my-vehicles', [
            'nomor_polisi' => 'DT 6262 VC',
        ])->assertForbidden();
    }

    public function test_regional_manager_can_disable_and_reset_spv_with_token_revocation_and_audit(): void
    {
        $terminalId = $this->terminal('IT-KENDARI');
        $regional = $this->user('regional_manager', 'regional_spv_lifecycle');
        $spv = $this->user('admin', 'spv_lifecycle', $terminalId);
        $spvToken = $this->token($spv);
        $regionalToken = $this->token($regional);

        $this->withToken($regionalToken)
            ->patchJson("/api/v1/regional/spvs/{$spv->id}/status", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => (int) str($spvToken)->before('|')->toString(),
        ]);
        $this->assertDatabaseHas('audit_log', ['action' => 'SPV_STATUS', 'terminal_id' => $terminalId]);

        $this->app['auth']->forgetGuards();
        $this->withToken($regionalToken)
            ->patchJson("/api/v1/regional/spvs/{$spv->id}/status", ['status' => 'active'])
            ->assertOk();

        $newSpvToken = $this->token($spv->fresh());
        $this->app['auth']->forgetGuards();
        $this->withToken($regionalToken)
            ->postJson("/api/v1/regional/spvs/{$spv->id}/reset-password", [
                'password' => 'RegionalChanged123!',
            ])->assertOk();

        $this->assertTrue(Hash::check('RegionalChanged123!', $spv->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => (int) str($newSpvToken)->before('|')->toString(),
        ]);
        $this->assertDatabaseHas('audit_log', ['action' => 'SPV_PASSWORD_RESET', 'terminal_id' => $terminalId]);
    }
}
