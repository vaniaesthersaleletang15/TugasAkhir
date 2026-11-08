<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function test_dashboard_period_filter_is_applied_to_summary_and_terminal_comparison(): void
    {
        $terminalId = DB::table('integrated_terminals')->insertGetId([
            'code' => 'IT-PERIOD-TEST',
            'name' => 'Terminal Period Test',
            'region_code' => 'SULAWESI',
            'region_name' => 'Regional Sulawesi',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach ([
            ['2026-01-10', 'approved', 'DB 1001 AA'],
            ['2026-01-11', 'pending_hsse', 'DB 1002 AA'],
            ['2026-02-10', 'rejected', 'DB 1003 AA'],
        ] as [$date, $status, $plate]) {
            DB::table('formulir_checklist')->insert([
                'terminal_id' => $terminalId,
                'jenis_kendaraan' => 'SPBU',
                'nomor_polisi' => $plate,
                'tanggal_pemeriksaan' => $date,
                'status_approval' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $token = $this->regionalToken();
        $response = $this->withToken($token)->getJson(
            "/api/v1/regional/dashboard?terminal_id={$terminalId}&date_from=2026-01-01&date_to=2026-01-31"
        )->assertOk();

        $response->assertJsonPath('data.summary.checklists', 2)
            ->assertJsonPath('data.summary.approved', 1)
            ->assertJsonPath('data.summary.pending', 1)
            ->assertJsonPath('data.summary.rejected', 0)
            ->assertJsonPath('data.comparison.0.checklists', 2)
            ->assertJsonPath('data.comparison.0.approved', 1)
            ->assertJsonPath('data.comparison.0.pending', 1)
            ->assertJsonPath('data.comparison.0.rejected', 0);
        $this->withToken($token)->getJson(
            "/api/v1/checklists?terminal_id={$terminalId}&dateFrom=2026-01-01&dateTo=2026-01-31&status=pending"
        )->assertOk()->assertJsonPath('pagination.total', 1);
        $this->withToken($token)->getJson(
            "/api/v1/checklists/stats?terminal_id={$terminalId}&dateFrom=2026-01-01&dateTo=2026-01-31&status=pending"
        )->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.pending', 1);
    }

    public function test_regional_vehicle_drill_down_is_scoped_filterable_and_paginated(): void
    {
        $inside = DB::table('integrated_terminals')->where('code', 'IT-BITUNG')->value('id');
        $outside = DB::table('integrated_terminals')->insertGetId([
            'code' => 'IT-OUTSIDE-VEHICLE', 'name' => 'Terminal Outside', 'region_code' => 'OTHER',
            'region_name' => 'Other', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $transporterId = DB::table('transporters')->insertGetId([
            'terminal_id' => $inside, 'code' => 'TR-REGIONAL', 'name' => 'PT Transportir Regional',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (range(1, 3) as $index) {
            DB::table('kendaraan')->insert([
                'terminal_id' => $inside, 'transporter_id' => $transporterId, 'jenis' => 'SPBU',
                'nomor_polisi' => "DB 90{$index} AA", 'merk_mobil' => 'Hino', 'nama_transport' => null,
                'status' => 'AKTIF', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('kendaraan')->insert([
            'terminal_id' => $outside, 'jenis' => 'SPBU', 'nomor_polisi' => 'B 9999 XX',
            'merk_mobil' => 'Hino', 'nama_transport' => 'PT Outside', 'status' => 'AKTIF',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $token = $this->regionalToken();
        $this->withToken($token)->getJson('/api/v1/regional/vehicles?search=Transportir%20Regional&limit=2&page=1')
            ->assertOk()->assertJsonPath('pagination.total', 3)->assertJsonCount(2, 'data')
            ->assertJsonMissing(['nomor_polisi' => 'B 9999 XX']);
        $this->withToken($token)->getJson('/api/v1/regional/vehicles?search=Transportir%20Regional&limit=2&page=2')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_regional_manager_can_download_only_approved_documents_in_own_region(): void
    {
        Storage::fake('private');
        $terminalId = DB::table('integrated_terminals')->where('code', 'IT-BITUNG')->value('id');
        $vehicleId = DB::table('kendaraan')->insertGetId([
            'terminal_id' => $terminalId, 'jenis' => 'SPBU', 'nomor_polisi' => 'DB 8181 AA',
            'merk_mobil' => 'Hino', 'nama_transport' => 'PT Dokumen', 'status' => 'AKTIF',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Storage::disk('private')->put('dokumen/approved.pdf', 'approved');
        Storage::disk('private')->put('dokumen/pending.pdf', 'pending');
        $approved = DB::table('dokumen_kendaraan')->insertGetId([
            'terminal_id' => $terminalId, 'vehicle_id' => $vehicleId, 'nomor_polisi' => 'DB 8181 AA',
            'jenis_dokumen' => 'STNK', 'nama_file_asli' => 'stnk.pdf', 'file_path' => 'dokumen/approved.pdf',
            'status' => 'DISETUJUI', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $pending = DB::table('dokumen_kendaraan')->insertGetId([
            'terminal_id' => $terminalId, 'vehicle_id' => $vehicleId, 'nomor_polisi' => 'DB 8181 AA',
            'jenis_dokumen' => 'PAJAK', 'nama_file_asli' => 'pajak.pdf', 'file_path' => 'dokumen/pending.pdf',
            'status' => 'PENDING', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $token = $this->regionalToken();
        $this->withToken($token)->get("/api/v1/regional/documents/{$approved}/download")->assertOk();
        $this->withToken($token)->get("/api/v1/regional/documents/{$pending}/download")->assertNotFound();
    }

    public function test_regional_audit_is_paginated(): void
    {
        $terminalId = DB::table('integrated_terminals')->where('code', 'IT-BITUNG')->value('id');
        foreach (range(1, 25) as $index) {
            DB::table('audit_log')->insert([
                'terminal_id' => $terminalId, 'action' => 'TEST', 'user_name' => 'tester',
                'description' => "Audit {$index}", 'created_at' => now()->addSeconds($index),
            ]);
        }

        $this->withToken($this->regionalToken())->getJson('/api/v1/regional/audits?limit=10&page=2')
            ->assertOk()->assertJsonPath('pagination.total', 25)->assertJsonPath('pagination.page', 2)
            ->assertJsonCount(10, 'data');
    }
}
