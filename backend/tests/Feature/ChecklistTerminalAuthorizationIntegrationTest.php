<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChecklistTerminalAuthorizationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function terminal(string $code): int
    {
        return (int) DB::table('integrated_terminals')->where('code', $code)->value('id');
    }

    private function user(string $role, string $username, int $terminalId): User
    {
        return User::create([
            'username' => $username,
            'full_name' => ucfirst($username),
            'email' => "$username@example.test",
            'password' => 'Secret123!',
            'role' => $role,
            'terminal_id' => $terminalId,
            'status' => 'active',
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('terminal-authorization')->plainTextToken;
    }

    private function vehicle(int $terminalId, string $plate): int
    {
        return (int) DB::table('kendaraan')->insertGetId([
            'terminal_id' => $terminalId,
            'jenis' => 'SPBU',
            'nomor_polisi' => $plate,
            'merk_mobil' => 'Hino',
            'nama_transport' => 'PT Terminal Asing',
            'status' => 'AKTIF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function checklist(int $terminalId, string $plate, string $status = 'draft'): int
    {
        $id = DB::table('formulir_checklist')->insertGetId([
            'terminal_id' => $terminalId,
            'jenis_kendaraan' => 'SPBU',
            'nomor_polisi' => $plate,
            'tanggal_pemeriksaan' => today(),
            'ekim_valid_until' => today()->addYear(),
            'status_approval' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('checklist_items')->insert([
            'formulir_id' => $id,
            'item_number' => 1,
            'item_name' => 'Safety switch',
            'is_baik' => true,
            'is_tidak' => false,
        ]);

        return (int) $id;
    }

    private function payload(int $vehicleId): array
    {
        return [
            'selectedVehicleId' => $vehicleId,
            'jenisKendaraan' => 'SPBU',
            'tanggalPemeriksaan' => today()->toDateString(),
            'ekimValidUntil' => today()->addYear()->toDateString(),
            'namaPemeriksaBagian' => 'Petugas Bitung',
            'tanggalPemeriksaBagian' => today()->toDateString(),
            'checklist' => [[
                'nama' => 'Safety switch',
                'baik' => true,
                'tidak' => false,
                'keterangan' => 'Baik',
                'tanggal_expire' => '',
            ]],
        ];
    }

    public function test_internal_user_cannot_create_checklist_from_vehicle_in_another_terminal(): void
    {
        $bitung = $this->terminal('IT-BITUNG');
        $kendari = $this->terminal('IT-KENDARI');
        $admin = $this->user('admin', 'foreign_create_admin', $bitung);
        $foreignVehicle = $this->vehicle($kendari, 'DT 9001 XA');

        $this->withToken($this->token($admin))
            ->postJson('/api/v1/checklists', $this->payload($foreignVehicle))
            ->assertForbidden();

        $this->assertDatabaseCount('formulir_checklist', 0);
    }

    public function test_internal_user_cannot_update_delete_or_change_workflow_in_another_terminal(): void
    {
        $bitung = $this->terminal('IT-BITUNG');
        $kendari = $this->terminal('IT-KENDARI');
        $admin = $this->user('admin', 'foreign_mutation_admin', $bitung);
        $foreignVehicle = $this->vehicle($kendari, 'DT 9002 XB');
        $foreignChecklist = $this->checklist($kendari, 'DT 9002 XB');
        $token = $this->token($admin);

        $this->withToken($token)
            ->putJson("/api/v1/checklists/$foreignChecklist", $this->payload($foreignVehicle))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->postJson('/api/v1/checklists/workflow', [
                'formulir_id' => $foreignChecklist,
                'action' => 'submit',
            ])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->deleteJson("/api/v1/checklists/$foreignChecklist")
            ->assertForbidden();

        $this->assertDatabaseHas('formulir_checklist', [
            'id' => $foreignChecklist,
            'status_approval' => 'draft',
        ]);
    }

    public function test_hsse_and_manager_cannot_sign_checklist_in_another_terminal(): void
    {
        $bitung = $this->terminal('IT-BITUNG');
        $kendari = $this->terminal('IT-KENDARI');
        $hsse = $this->user('hsse', 'foreign_signature_hsse', $bitung);
        $manager = $this->user('manager_hsse', 'foreign_signature_manager', $bitung);
        $pending = $this->checklist($kendari, 'DT 9003 XC', 'pending_hsse');
        $signed = $this->checklist($kendari, 'DT 9004 XD', 'signed_hsse');

        $this->withToken($this->token($hsse))->postJson('/api/v1/checklists/signature', [
            'formulir_id' => $pending,
            'role' => 'hsse',
            'canvas_image' => 'data:image/png;base64,iVBORw0KGgo=',
            'signer_name' => $hsse->full_name,
        ])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($manager))->postJson('/api/v1/checklists/signature', [
            'formulir_id' => $signed,
            'role' => 'manajer',
            'canvas_image' => 'data:image/png;base64,iVBORw0KGgo=',
            'signer_name' => $manager->full_name,
        ])->assertForbidden();

        $this->assertDatabaseMissing('digital_signature_log', ['formulir_id' => $pending]);
        $this->assertDatabaseMissing('digital_signature_log', ['formulir_id' => $signed]);
    }
}
