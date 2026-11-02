<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChecklistLifecycleIntegrationTest extends TestCase
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
        return $user->createToken('checklist-integration')->plainTextToken;
    }

    private function vehicle(string $plate = 'DB 7070 QA', ?int $terminalId = null): int
    {
        return (int) DB::table('kendaraan')->insertGetId([
            'terminal_id' => $terminalId ?? $this->terminal(),
            'jenis' => 'SPBU',
            'nomor_polisi' => $plate,
            'merk_mobil' => 'Hino',
            'tahun_kendaraan' => 2025,
            'nama_transport' => 'PT Integrasi',
            'status' => 'AKTIF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function payload(int $vehicleId, string $note = 'Baik'): array
    {
        return [
            'selectedVehicleId' => $vehicleId,
            'jenisKendaraan' => 'SPBU',
            'nomorUrut' => 'INT-001',
            'tanggalPemeriksaan' => today()->toDateString(),
            'ekimValidUntil' => today()->addYear()->toDateString(),
            'statusGate' => 'OK',
            'statusUpload' => 'OK',
            'namaPemeriksaBagian' => 'Petugas Integrasi',
            'tanggalPemeriksaBagian' => today()->toDateString(),
            'checklist' => [[
                'nama' => 'Safety switch',
                'baik' => true,
                'tidak' => false,
                'keterangan' => $note,
                'tanggal_expire' => '',
            ]],
        ];
    }

    public function test_draft_can_be_created_read_updated_and_deleted_with_items(): void
    {
        $admin = $this->user('admin', 'draft_lifecycle_admin');
        $vehicleId = $this->vehicle();
        $token = $this->token($admin);

        $id = $this->withToken($token)
            ->postJson('/api/v1/checklists', $this->payload($vehicleId))
            ->assertOk()
            ->json('data.id');

        $this->assertDatabaseHas('formulir_checklist', ['id' => $id, 'status_approval' => 'draft']);
        $this->assertDatabaseHas('checklist_items', ['formulir_id' => $id, 'keterangan' => 'Baik']);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson("/api/v1/checklists/$id")
            ->assertOk()
            ->assertJsonPath('data.viewer_can_sign_hsse', true)
            ->assertJsonCount(1, 'data.checklist_items');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->putJson("/api/v1/checklists/$id", $this->payload($vehicleId, 'Diperbarui'))
            ->assertOk()
            ->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('checklist_items', 1);
        $this->assertDatabaseHas('checklist_items', ['formulir_id' => $id, 'keterangan' => 'Diperbarui']);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->deleteJson("/api/v1/checklists/$id")->assertOk();
        $this->assertDatabaseMissing('formulir_checklist', ['id' => $id]);
        $this->assertDatabaseMissing('checklist_items', ['formulir_id' => $id]);
        $this->assertDatabaseHas('audit_log', ['action' => 'DELETE']);
    }

    public function test_submitted_checklist_blocks_edit_and_delete_until_admin_resets_draft(): void
    {
        $admin = $this->user('admin', 'workflow_lifecycle_admin');
        $hsse = $this->user('hsse', 'workflow_lifecycle_hsse');
        $vehicleId = $this->vehicle('DB 7171 QB');
        $token = $this->token($admin);
        $id = $this->withToken($token)
            ->postJson('/api/v1/checklists', $this->payload($vehicleId))
            ->assertOk()
            ->json('data.id');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/checklists/workflow', [
            'formulir_id' => $id,
            'action' => 'submit',
        ])->assertOk()->assertJsonPath('data.new_status', 'pending_hsse');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->putJson("/api/v1/checklists/$id", $this->payload($vehicleId, 'Tidak boleh tersimpan'))
            ->assertStatus(409);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($hsse))->deleteJson("/api/v1/checklists/$id")->assertStatus(409);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/checklists/workflow', [
            'formulir_id' => $id,
            'action' => 'reset_draft',
        ])->assertOk()->assertJsonPath('data.new_status', 'draft');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->deleteJson("/api/v1/checklists/$id")->assertOk();
    }

    public function test_dashboard_stats_templates_lists_and_transporters_use_same_terminal_scope(): void
    {
        $bitung = $this->terminal();
        $kendari = $this->terminal('IT-KENDARI');
        $admin = $this->user('admin', 'query_admin', $bitung);
        $this->user('pengurus', 'query_owner_bitung', $bitung);
        $this->user('pengurus', 'query_owner_kendari', $kendari);
        foreach ([[$bitung, 'DB 7272 QC'], [$kendari, 'DT 7373 QD']] as [$terminalId, $plate]) {
            DB::table('formulir_checklist')->insert([
                'terminal_id' => $terminalId,
                'jenis_kendaraan' => 'SPBU',
                'nomor_polisi' => $plate,
                'tanggal_pemeriksaan' => today(),
                'ekim_valid_until' => today()->addYear(),
                'status_approval' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $token = $this->token($admin);

        $this->withToken($token)->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.checklists', 1);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/checklists/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.spbu', 1);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/checklists?status=draft&limit=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nomor_polisi', 'DB 7272 QC');

        $this->app['auth']->forgetGuards();
        $templateResponse = $this->withToken($token)->getJson('/api/v1/checklists/template?jenis=SPBU')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(25, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'nama',
                    'pelaksana',
                    'prioritas',
                    'nomor',
                    'rowspan',
                ]],
            ]);

        $this->assertNotEmpty($templateResponse->json('data.0.nama'));

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/transporters')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'query_owner_bitung@example.test');
    }

    public function test_checklist_detail_is_not_visible_across_terminal_boundary(): void
    {
        $adminBitung = $this->user('admin', 'detail_bitung', $this->terminal());
        $foreignId = DB::table('formulir_checklist')->insertGetId([
            'terminal_id' => $this->terminal('IT-KENDARI'),
            'jenis_kendaraan' => 'SPBU',
            'nomor_polisi' => 'DT 7474 QE',
            'tanggal_pemeriksaan' => today(),
            'status_approval' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken($this->token($adminBitung))
            ->getJson("/api/v1/checklists/$foreignId")
            ->assertNotFound();
    }
}
