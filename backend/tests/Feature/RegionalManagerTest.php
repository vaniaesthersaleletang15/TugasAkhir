<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegionalManagerTest extends TestCase
{
    use RefreshDatabase;

    private function regionalToken(): string
    {
        User::create([
            'username' => 'regional_manager_test',
            'full_name' => 'Regional Manager Supply & Distribution',
            'email' => 'regional@example.test',
            'password' => 'Secret123!',
            'role' => 'regional_manager',
            'region_code' => 'SULAWESI',
            'status' => 'active',
        ]);

        return $this->postJson('/api/v1/auth/login', [
            'email' => 'regional@example.test',
            'password' => 'Secret123!',
            'portal' => 'internal',
        ])->assertOk()->assertJsonPath('data.user.role', 'regional_manager')->json('data.token');
    }

    public function test_regional_manager_can_monitor_all_sulawesi_terminals(): void
    {
        $bitung = DB::table('integrated_terminals')->where('code', 'IT-BITUNG')->value('id');
        $second = DB::table('integrated_terminals')->insertGetId([
            'code' => 'FT-TEST', 'name' => 'Fuel Terminal Test', 'city' => 'Makassar',
            'region_code' => 'SULAWESI', 'region_name' => 'Regional Sulawesi', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $outside = DB::table('integrated_terminals')->insertGetId([
            'code' => 'FT-OUTSIDE', 'name' => 'Terminal Di Luar Region', 'city' => 'Luar',
            'region_code' => 'OTHER', 'region_name' => 'Regional Lain', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[$bitung, 'DB 1001 AA'], [$second, 'DD 2002 BB'], [$outside, 'B 3003 CC']] as [$terminalId, $plate]) {
            DB::table('kendaraan')->insert([
                'terminal_id' => $terminalId, 'jenis' => 'SPBU', 'nomor_polisi' => $plate,
                'merk_mobil' => 'Hino', 'nama_transport' => 'Transportir Test',
                'ekim_valid_until' => today()->addDays($terminalId === $second ? 10 : 60),
                'status' => 'AKTIF', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('formulir_checklist')->insert([
                'terminal_id' => $terminalId, 'jenis_kendaraan' => 'SPBU', 'nomor_polisi' => $plate,
                'nama_transport' => 'Transportir Test', 'tanggal_pemeriksaan' => today(),
                'ekim_valid_until' => today()->addDays(60), 'status_approval' => 'approved',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('audit_log')->insert([
            'terminal_id' => $second, 'action' => 'UPDATE', 'user_name' => 'hsse_test',
            'description' => 'Aktivitas terminal', 'created_at' => now(),
        ]);
        $token = $this->regionalToken();

        $this->withToken($token)->getJson('/api/v1/regional/terminals')
            ->assertOk()->assertJsonCount(6, 'data');
        $this->withToken($token)->getJson('/api/v1/regional/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.terminals', 6)
            ->assertJsonPath('data.summary.vehicles', 2)
            ->assertJsonPath('data.summary.checklists', 2);
        $this->withToken($token)->getJson('/api/v1/checklists')
            ->assertOk()->assertJsonPath('pagination.total', 2)
            ->assertJsonMissing(['nomor_polisi' => 'B 3003 CC']);
        $this->withToken($token)->getJson("/api/v1/regional/ekim?terminal_id={$second}&status=expiring")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nomor_polisi', 'DD 2002 BB');
        $this->withToken($token)->getJson('/api/v1/regional/report')->assertOk();
        $this->withToken($token)->getJson('/api/v1/regional/audits')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_regional_manager_is_rejected_by_mutating_endpoints(): void
    {
        $token = $this->regionalToken();

        $this->withToken($token)->postJson('/api/v1/checklists', [])->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/checklists/workflow', ['formulir_id' => 1, 'action' => 'submit'])->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/checklists/signature', [
            'formulir_id' => 1, 'role' => 'manajer', 'canvas_image' => 'data:image/png;base64,iVBORw0KGgo=', 'signer_name' => 'Regional Manager Supply & Distribution',
        ])->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/users', [])->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/vehicles/manage', [])->assertForbidden();
        $this->withToken($token)->putJson('/api/v1/settings', [])->assertForbidden();
    }

    public function test_terminals_are_fixed_and_regional_manager_creates_spv(): void
    {
        $token = $this->regionalToken();
        $kendari = DB::table('integrated_terminals')->where('code', 'IT-KENDARI')->value('id');
        $this->assertDatabaseHas('integrated_terminals', ['code' => 'IT-BITUNG', 'is_active' => true]);
        $this->assertDatabaseHas('integrated_terminals', ['code' => 'IT-GORONTALO', 'is_active' => true]);
        $this->assertDatabaseHas('integrated_terminals', ['code' => 'IT-DONGGALA', 'is_active' => true]);
        $this->assertDatabaseHas('integrated_terminals', ['code' => 'IT-KENDARI', 'is_active' => true]);
        $this->assertDatabaseHas('integrated_terminals', ['code' => 'IT-MAKASSAR', 'is_active' => true]);

        $payload = ['full_name' => 'SPV HSSE Kendari', 'email' => 'spv-kendari@example.test', 'phone' => '0812345', 'terminal_id' => $kendari, 'password' => 'Strong123!'];
        $id = $this->withToken($token)->postJson('/api/v1/regional/spvs', $payload)
            ->assertCreated()->assertJsonPath('success', true)->json('data.id');
        $this->assertDatabaseHas('users', ['id' => $id, 'role' => 'admin', 'terminal_id' => $kendari, 'status' => 'active']);
        $this->withToken($token)->getJson('/api/v1/regional/spvs')->assertOk()->assertJsonFragment(['email' => 'spv-kendari@example.test']);
        $this->withToken($token)->postJson('/api/v1/terminals', [])->assertNotFound();
    }
}
